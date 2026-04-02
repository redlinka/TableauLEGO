import { randomUUID } from "node:crypto";
import {
  ConnectionState,
  EventType,
  GameMode,
  GameType,
  HistoryOutcome,
  INITIAL_BRICK_SHAPES,
  LINE_BREAKER_COLOR_POOL,
  LineBreakerHistoryMetadata,
  LineBreakerSessionState,
  PlayerSeatType,
  SessionStatus,
  TechnicalPlayerIdentity
} from "@games-platform/game-contracts";
import { AppConfig } from "../../config/env.js";
import {
  applyPlacement,
  createEmptyBoard,
  hasAnyValidPlacementForShape,
  hydrateBoardState,
  listValidPlacementsForShape,
  serializeBoard,
  validatePlacement
} from "../common/boardEngine.js";
import { DeterministicBrickSequence } from "../common/deterministicSequence.js";
import {
  calculateTurnTimeBonus,
  computeRemainingTurnMs,
  createTimedTurnState,
  resolveSessionSeed,
  toGameSessionSummary
} from "../common/soloSessionSupport.js";
import { calculateLoyaltyReward, LoyaltyPolicyConfig } from "../common/loyalty.js";
import { fetchLoyaltyPolicy, toLoyaltyPolicyConfig } from "../common/loyaltyPolicyFetcher.js";
import { calculateLineBreakerScore } from "./score.js";
import {
  clearCompletedLines,
  detectCompletedMonochromeLines
} from "./lineRules.js";
import { LineBreakerPersistedState } from "./types.js";
import { GameSessionDocument } from "../../storage/mongoose/models/GameSessionModel.js";
import { RepositoryBundle } from "../../storage/mongoose/repositories/index.js";

const SOLO_RULESET_VERSION = "line_breaker_solo_v1";

export class SoloLineBreakerSessionNotFoundError extends Error {
  constructor(sessionId: string) {
    super(`Unknown line_breaker solo session '${sessionId}'.`);
    this.name = "SoloLineBreakerSessionNotFoundError";
  }
}

function parsePersistedState(sessionId: string, value: unknown): LineBreakerPersistedState {
  if (!value || typeof value !== "object") {
    throw new Error(`Missing persisted state for session '${sessionId}'.`);
  }

  const candidate = value as Partial<LineBreakerPersistedState>;

  if (!candidate.board || !candidate.turn || !candidate.lineClearStats || !candidate.metrics) {
    throw new Error(`Invalid persisted state for session '${sessionId}'.`);
  }

  return candidate as LineBreakerPersistedState;
}

export class SoloLineBreakerService {
  private readonly shapesById = new Map(
    INITIAL_BRICK_SHAPES.map((shape) => [shape.shapeId, shape])
  );
  private readonly nowProvider: () => Date;

  constructor(
    private readonly dependencies: {
      config: Pick<AppConfig, "soloTurnLimitMs" | "lineBreakerMaxSequenceLength" | "phpApiUrl">;
      repositories: RepositoryBundle;
      now?: () => Date;
    }
  ) {
    this.nowProvider = dependencies.now ?? (() => new Date());
  }

  private async getLoyaltyPolicyConfig(): Promise<LoyaltyPolicyConfig> {
    const policy = await fetchLoyaltyPolicy(this.dependencies.config.phpApiUrl);
    return toLoyaltyPolicyConfig(policy);
  }

  async createSession(input: {
    player: TechnicalPlayerIdentity;
    baseUrl: string;
    seed?: string;
  }): Promise<LineBreakerSessionState> {
    const createdAt = this.nowProvider();
    const seed = resolveSessionSeed(input.seed);
    const sessionId = `ses_${randomUUID()}`;
    const sequence = this.createSequence(seed);

    let state = this.buildInitialState(sequence, createdAt);
    let status = SessionStatus.Active;
    let endedAt: Date | undefined;

    if (state.finished) {
      status = SessionStatus.Completed;
      endedAt = createdAt;
    }

    const sessionDocument: Omit<GameSessionDocument, "createdAt" | "updatedAt"> = {
      sessionId,
      mode: GameMode.Solo,
      gameType: GameType.LineBreaker,
      status,
      rulesetVersion: SOLO_RULESET_VERSION,
      seed,
      hostPlayerId: input.player.playerId,
      players: [
        {
          seat: PlayerSeatType.Solo,
          playerId: input.player.playerId,
          publicAlias: input.player.publicAlias,
          connectionState: ConnectionState.Connected,
          ready: true,
          score: state.score.score
        }
      ],
      currentTurn: state.turn.index,
      sessionState: state,
      result: state.result,
      sharedClock: {
        startedAt: createdAt,
        durationMs: this.dependencies.config.soloTurnLimitMs,
        remainingMs: this.computeRemainingMs(state, createdAt),
        tickRateMs: 1000
      },
      startedAt: createdAt,
      endedAt
    };

    await this.dependencies.repositories.gameSessions.create(sessionDocument);
    await this.dependencies.repositories.gameEvents.append({
      sessionId,
      seq: 1,
      type: EventType.SessionCreated,
      actorPlayerId: input.player.playerId,
      payload: {
        gameType: GameType.LineBreaker,
        mode: GameMode.Solo,
        seed
      }
    });
    await this.dependencies.repositories.gameEvents.append({
      sessionId,
      seq: 2,
      type: EventType.SessionStarted,
      actorPlayerId: input.player.playerId,
      payload: {
        turn: state.turn.index,
        offerId: state.currentOffer?.offerId ?? null
      }
    });

    if (state.finished && endedAt) {
      await this.appendFinishedEvent(sessionId, state.eventSeq, input.player.playerId, state);
      await this.persistHistoryEntry({
        sessionId,
        player: input.player,
        status,
        startedAt: createdAt,
        endedAt,
        state
      });
    }

    return this.toPublicState(
      {
        ...sessionDocument,
        createdAt,
        updatedAt: createdAt
      },
      state
    );
  }

  async getSessionState(input: {
    playerId: string;
    sessionId: string;
    baseUrl: string;
  }): Promise<LineBreakerSessionState> {
    const context = await this.loadSessionContext(input.sessionId, input.playerId);
    const synchronized = await this.synchronizeSessionIfNeeded(context);

    return this.toPublicState(synchronized.document, synchronized.state);
  }

  async submitPlacement(input: {
    player: TechnicalPlayerIdentity;
    sessionId: string;
    baseUrl: string;
    anchorX: number;
    anchorY: number;
    rotation: number;
  }): Promise<{
    accepted: boolean;
    rejectionReason?: string;
    state: LineBreakerSessionState;
  }> {
    const context = await this.loadSessionContext(input.sessionId, input.player.playerId);
    const synchronized = await this.synchronizeSessionIfNeeded(context);

    if (synchronized.state.finished || !synchronized.state.currentOffer) {
      return {
        accepted: false,
        rejectionReason: "session_finished",
        state: this.toPublicState(synchronized.document, synchronized.state)
      };
    }

    const shape = this.getShapeOrThrow(synchronized.state.currentOffer.shapeId);
    const validation = validatePlacement({
      board: hydrateBoardState(synchronized.state.board),
      shape,
      rotation: input.rotation,
      anchorX: input.anchorX,
      anchorY: input.anchorY
    });

    if (!validation.valid) {
      const invalidState = this.refreshComputedState({
        ...synchronized.state,
        metrics: {
          ...synchronized.state.metrics,
          invalidAttempts: synchronized.state.metrics.invalidAttempts + 1,
          lastClearedLines: [],
          lastClearedCellCount: 0
        }
      });
      const nextDocument = this.buildUpdatedDocument(
        synchronized.document,
        invalidState,
        synchronized.document.status
      );

      await this.saveDocumentState(nextDocument, invalidState);

      return {
        accepted: false,
        rejectionReason: validation.reason,
        state: this.toPublicState(nextDocument, invalidState)
      };
    }

    const placementTimeBonus = this.calculatePlacementTimeBonus(synchronized.state);
    const placedBoard = applyPlacement({
      board: hydrateBoardState(synchronized.state.board),
      shape,
      offer: synchronized.state.currentOffer,
      rotation: input.rotation,
      anchorX: input.anchorX,
      anchorY: input.anchorY,
      turn: synchronized.state.turn.index
    });
    const completedLines = detectCompletedMonochromeLines(placedBoard);
    const cleared = clearCompletedLines({
      board: placedBoard,
      lines: completedLines
    });
    const lineClearStats = this.updateLineClearStats(
      synchronized.state.lineClearStats,
      completedLines.length,
      completedLines.filter((line) => line.orientation === "row").length,
      completedLines.filter((line) => line.orientation === "column").length,
      cleared.clearedCellCount
    );

    let nextState = this.refreshComputedState({
      ...synchronized.state,
      board: serializeBoard(cleared.board),
      lineClearStats,
      metrics: {
        ...synchronized.state.metrics,
        occupiedCells: cleared.board.occupiedCount,
        totalPlacedCells:
          synchronized.state.metrics.totalPlacedCells + validation.absoluteCells.length,
        placementsAccepted: synchronized.state.metrics.placementsAccepted + 1,
        lastClearedLines: completedLines.map((line) => ({
          orientation: line.orientation,
          index: line.index,
          color: line.color,
          cellCount: line.cellCount
        })),
        lastClearedCellCount: cleared.clearedCellCount
      },
      accumulatedTimeBonus: synchronized.state.accumulatedTimeBonus + placementTimeBonus,
      eventSeq: synchronized.state.eventSeq + 1
    });

    await this.dependencies.repositories.gameEvents.append({
      sessionId: synchronized.document.sessionId,
      seq: nextState.eventSeq,
      type: EventType.BrickPlaced,
      actorPlayerId: input.player.playerId,
      payload: {
        offerId: synchronized.state.currentOffer.offerId,
        shapeId: synchronized.state.currentOffer.shapeId,
        color: synchronized.state.currentOffer.color,
        rotation: input.rotation,
        anchorX: input.anchorX,
        anchorY: input.anchorY,
        turn: synchronized.state.turn.index,
        clearedLines: nextState.metrics.lastClearedLines,
        clearedCellCount: nextState.metrics.lastClearedCellCount,
        comboAfterTurn: nextState.lineClearStats.currentCombo
      }
    });

    const now = this.nowProvider();
    nextState = this.advanceAfterResolvedTurn(nextState, synchronized.sequence, now);
    let nextStatus = synchronized.document.status;

    if (nextState.finished) {
      nextStatus =
        nextState.finishReason === "abandoned"
          ? SessionStatus.Abandoned
          : SessionStatus.Completed;
      nextState = this.finalizeState(
        {
          ...nextState,
          eventSeq: nextState.eventSeq + 1
        },
        nextState.finishReason ?? "sequence_exhausted",
        now
      );
      await this.appendFinishedEvent(
        synchronized.document.sessionId,
        nextState.eventSeq,
        input.player.playerId,
        nextState
      );
    }

    const nextDocument = this.buildUpdatedDocument(
      synchronized.document,
      nextState,
      nextStatus,
      nextStatus === SessionStatus.Active ? undefined : now
    );

    await this.saveDocumentState(nextDocument, nextState);

    if (nextStatus !== SessionStatus.Active) {
      await this.persistHistoryEntry({
        sessionId: synchronized.document.sessionId,
        player: input.player,
        status: nextStatus,
        startedAt: synchronized.document.startedAt ?? synchronized.document.createdAt,
        endedAt: now,
        state: nextState
      });
    }

    return {
      accepted: true,
      state: this.toPublicState(nextDocument, nextState)
    };
  }

  async abandonSession(input: {
    player: TechnicalPlayerIdentity;
    sessionId: string;
    baseUrl: string;
  }): Promise<LineBreakerSessionState> {
    const context = await this.loadSessionContext(input.sessionId, input.player.playerId);
    const synchronized = await this.synchronizeSessionIfNeeded(context);

    if (synchronized.state.finished) {
      return this.toPublicState(synchronized.document, synchronized.state);
    }

    const endedAt = this.nowProvider();
    const abandonedState = this.finalizeState(
      {
        ...synchronized.state,
        eventSeq: synchronized.state.eventSeq + 1
      },
      "abandoned",
      endedAt
    );

    await this.appendFinishedEvent(
      synchronized.document.sessionId,
      abandonedState.eventSeq,
      input.player.playerId,
      abandonedState
    );

    const nextDocument = this.buildUpdatedDocument(
      synchronized.document,
      abandonedState,
      SessionStatus.Abandoned,
      endedAt
    );

    await this.saveDocumentState(nextDocument, abandonedState);
    await this.persistHistoryEntry({
      sessionId: synchronized.document.sessionId,
      player: input.player,
      status: SessionStatus.Abandoned,
      startedAt: synchronized.document.startedAt ?? synchronized.document.createdAt,
      endedAt,
      state: abandonedState
    });

    return this.toPublicState(nextDocument, abandonedState);
  }

  async getResult(input: {
    playerId: string;
    sessionId: string;
    baseUrl: string;
  }): Promise<LineBreakerSessionState> {
    return this.getSessionState(input);
  }

  private async loadSessionContext(sessionId: string, playerId: string): Promise<{
    document: GameSessionDocument;
    state: LineBreakerPersistedState;
    sequence: DeterministicBrickSequence;
  }> {
    const document = await this.dependencies.repositories.gameSessions.findOwnedSessionDocument(
      sessionId,
      playerId
    );

    if (
      !document ||
      document.mode !== GameMode.Solo ||
      document.gameType !== GameType.LineBreaker
    ) {
      throw new SoloLineBreakerSessionNotFoundError(sessionId);
    }

    return {
      document,
      state: parsePersistedState(document.sessionId, document.sessionState),
      sequence: this.createSequence(document.seed)
    };
  }

  private async synchronizeSessionIfNeeded(context: {
    document: GameSessionDocument;
    state: LineBreakerPersistedState;
    sequence: DeterministicBrickSequence;
  }): Promise<{
    document: GameSessionDocument;
    state: LineBreakerPersistedState;
    sequence: DeterministicBrickSequence;
  }> {
    if (context.state.finished || context.document.status !== SessionStatus.Active) {
      return context;
    }

    const nowMs = this.nowProvider().getTime();
    let nextState = context.state;
    let changed = false;

    while (!nextState.finished && nextState.turn.deadlineAt) {
      const deadlineMs = Date.parse(nextState.turn.deadlineAt);

      if (!Number.isFinite(deadlineMs) || nowMs < deadlineMs) {
        break;
      }

      changed = true;
      nextState = this.refreshComputedState({
        ...nextState,
        turn: {
          ...nextState.turn,
          skippedOffers: nextState.turn.skippedOffers + 1,
          expiredTurns: nextState.turn.expiredTurns + 1
        },
        metrics: {
          ...nextState.metrics,
          expiredTurns: nextState.metrics.expiredTurns + 1,
          lastClearedLines: [],
          lastClearedCellCount: 0
        }
      });
      nextState = this.advanceAfterResolvedTurn(nextState, context.sequence, new Date(deadlineMs));

      if (nextState.finished) {
        nextState = this.finalizeState(
          {
            ...nextState,
            eventSeq: nextState.eventSeq + 1
          },
          nextState.finishReason ?? "sequence_exhausted",
          new Date(deadlineMs)
        );
      }
    }

    if (!changed) {
      return context;
    }

    const nextStatus =
      nextState.finishReason === "abandoned"
        ? SessionStatus.Abandoned
        : nextState.finished
          ? SessionStatus.Completed
          : SessionStatus.Active;
    const endedAt = nextState.finished ? this.resolveEndedAt(nextState) : undefined;
    const nextDocument = this.buildUpdatedDocument(
      context.document,
      nextState,
      nextStatus,
      endedAt
    );

    await this.saveDocumentState(nextDocument, nextState);

    if (nextState.finished && endedAt) {
      await this.appendFinishedEvent(
        context.document.sessionId,
        nextState.eventSeq,
        context.document.hostPlayerId,
        nextState
      );

      const playerId = context.document.hostPlayerId ?? context.document.players[0]?.playerId;

      if (playerId) {
        const player = await this.dependencies.repositories.players.findByPlayerId(playerId);

        if (!player) {
          return {
            ...context,
            document: nextDocument,
            state: nextState
          };
        }

        await this.persistHistoryEntry({
          sessionId: context.document.sessionId,
          player,
          status: nextStatus,
          startedAt: context.document.startedAt ?? context.document.createdAt,
          endedAt,
          state: nextState
        });
      }
    }

    return {
      ...context,
      document: nextDocument,
      state: nextState
    };
  }

  private createSequence(seed: string): DeterministicBrickSequence {
    return new DeterministicBrickSequence(
      `${seed}:${SOLO_RULESET_VERSION}`,
      INITIAL_BRICK_SHAPES,
      LINE_BREAKER_COLOR_POOL.map((entry) => ({
        color: entry.color,
        weight: entry.weight
      }))
    );
  }

  private buildInitialState(
    sequence: DeterministicBrickSequence,
    createdAt: Date
  ): LineBreakerPersistedState {
    const currentOffer =
      this.dependencies.config.lineBreakerMaxSequenceLength > 0
        ? sequence.getOffer(0)
        : null;
    let state = this.refreshComputedState({
      board: serializeBoard(createEmptyBoard(10, 10)),
      turn: createTimedTurnState(1, this.dependencies.config.soloTurnLimitMs, createdAt.getTime()),
      currentOffer,
      availablePlacementCount: 0,
      score: {
        score: 0,
        linesCleared: 0,
        timeBonus: 0,
        breakdown: {
          placementPoints: 0,
          objectivePoints: 0,
          lineClearPoints: 0,
          comboPoints: 0,
          timeBonus: 0,
          penalties: 0
        }
      },
      lineClearStats: {
        totalLinesCleared: 0,
        horizontalLinesCleared: 0,
        verticalLinesCleared: 0,
        simultaneousClearTurns: 0,
        maxLinesSingleTurn: 0,
        totalClearedCells: 0,
        currentCombo: 0,
        maxCombo: 0,
        comboBonusTotal: 0,
        multiLineBonusTotal: 0
      },
      metrics: {
        occupiedCells: 0,
        totalPlacedCells: 0,
        placementsAccepted: 0,
        invalidAttempts: 0,
        expiredTurns: 0,
        lastClearedLines: [],
        lastClearedCellCount: 0
      },
      maxSequenceLength: this.dependencies.config.lineBreakerMaxSequenceLength,
      turnTimeLimitMs: this.dependencies.config.soloTurnLimitMs,
      finished: false,
      accumulatedTimeBonus: 0,
      eventSeq: 2
    });

    if (!state.currentOffer) {
      return this.finalizeState(
        {
          ...state,
          eventSeq: 3
        },
        "sequence_exhausted",
        createdAt
      );
    }

    if (state.availablePlacementCount === 0) {
      return this.finalizeState(
        {
          ...state,
          eventSeq: 3
        },
        "no_more_moves",
        createdAt
      );
    }

    return state;
  }

  private refreshComputedState(state: LineBreakerPersistedState): LineBreakerPersistedState {
    const availablePlacementCount = state.currentOffer
      ? listValidPlacementsForShape(
          hydrateBoardState(state.board),
          this.getShapeOrThrow(state.currentOffer.shapeId)
        ).length
      : 0;
    const metrics = {
      ...state.metrics,
      occupiedCells: state.board.occupiedCount,
      expiredTurns: state.turn.expiredTurns
    };
    const score = calculateLineBreakerScore({
      lineClearStats: state.lineClearStats,
      metrics,
      accumulatedTimeBonus: state.accumulatedTimeBonus
    });

    return {
      ...state,
      availablePlacementCount,
      metrics,
      score
    };
  }

  private advanceAfterResolvedTurn(
    state: LineBreakerPersistedState,
    sequence: DeterministicBrickSequence,
    baseTime: Date
  ): LineBreakerPersistedState {
    const currentSequenceIndex = state.currentOffer?.sequenceIndex ?? state.turn.index - 1;

    if (currentSequenceIndex + 1 >= state.maxSequenceLength) {
      return {
        ...state,
        currentOffer: null,
        availablePlacementCount: 0,
        finishReason: "sequence_exhausted",
        finished: true,
        turn: {
          ...state.turn,
          deadlineAt: undefined
        }
      };
    }

    const nextTurnIndex = state.turn.index + 1;
    const nextState = this.refreshComputedState({
      ...state,
      currentOffer: sequence.getOffer(nextTurnIndex - 1),
      turn: createTimedTurnState(nextTurnIndex, state.turnTimeLimitMs, baseTime.getTime(), {
        skippedOffers: state.turn.skippedOffers,
        expiredTurns: state.turn.expiredTurns
      }),
      finished: false,
      finishReason: undefined,
      result: undefined
    });

    if (
      nextState.currentOffer &&
      !hasAnyValidPlacementForShape(
        hydrateBoardState(nextState.board),
        this.getShapeOrThrow(nextState.currentOffer.shapeId)
      )
    ) {
      return {
        ...nextState,
        currentOffer: null,
        availablePlacementCount: 0,
        finished: true,
        finishReason: "no_more_moves",
        turn: {
          ...nextState.turn,
          deadlineAt: undefined
        }
      };
    }

    return nextState;
  }

  private finalizeState(
    state: LineBreakerPersistedState,
    reason: "no_more_moves" | "sequence_exhausted" | "abandoned",
    endedAt: Date
  ): LineBreakerPersistedState {
    const refreshed = this.refreshComputedState({
      ...state,
      finished: true,
      finishReason: reason,
      currentOffer: null,
      availablePlacementCount: 0,
      turn: {
        ...state.turn,
        deadlineAt: undefined
      }
    });
    const result = {
      ...refreshed.score,
      metadata: {
        ...(refreshed.score.metadata ?? {}),
        finishReason: reason,
        completedAt: endedAt.toISOString()
      }
    };

    return {
      ...refreshed,
      result
    };
  }

  private buildUpdatedDocument(
    document: GameSessionDocument,
    state: LineBreakerPersistedState,
    status: SessionStatus,
    endedAt?: Date
  ): GameSessionDocument {
    const playerScore = state.result?.score ?? state.score.score;

    return {
      ...document,
      status,
      currentTurn: state.turn.index,
      sessionState: state,
      result: state.result,
      sharedClock: {
        startedAt: document.sharedClock?.startedAt ?? document.startedAt ?? document.createdAt,
        durationMs: state.turnTimeLimitMs,
        remainingMs: this.computeRemainingMs(state, this.nowProvider()),
        tickRateMs: 1000
      },
      startedAt: document.startedAt ?? document.createdAt,
      endedAt: endedAt ?? document.endedAt,
      players: document.players.map((player, index) =>
        index === 0
          ? {
              ...player,
              score: playerScore
            }
          : player
      )
    };
  }

  private async saveDocumentState(
    document: GameSessionDocument,
    state: LineBreakerPersistedState
  ): Promise<void> {
    await this.dependencies.repositories.gameSessions.saveSnapshot({
      sessionId: document.sessionId,
      currentTurn: state.turn.index,
      status: document.status,
      sessionState: state,
      result: state.result,
      startedAt: document.startedAt,
      endedAt: document.endedAt,
      sharedClock: document.sharedClock,
      playerScore: state.result?.score ?? state.score.score
    });
  }

  private async appendFinishedEvent(
    sessionId: string,
    seq: number,
    actorPlayerId: string | undefined,
    state: LineBreakerPersistedState
  ): Promise<void> {
    await this.dependencies.repositories.gameEvents.append({
      sessionId,
      seq,
      type: EventType.SessionFinished,
      actorPlayerId,
      payload: {
        finishReason: state.finishReason,
        score: state.result?.score ?? state.score.score,
        totalLinesCleared: state.lineClearStats.totalLinesCleared,
        maxCombo: state.lineClearStats.maxCombo
      }
    });
  }

  private async persistHistoryEntry(input: {
    sessionId: string;
    player: TechnicalPlayerIdentity;
    status: SessionStatus;
    startedAt: Date;
    endedAt: Date;
    state: LineBreakerPersistedState;
  }): Promise<void> {
    const score = input.state.result?.score ?? input.state.score.score;
    const outcome: HistoryOutcome =
      input.status === SessionStatus.Abandoned ? "abandoned" : "solo";
    const policyConfig = await this.getLoyaltyPolicyConfig();
    const reward = calculateLoyaltyReward({
      gameType: GameType.LineBreaker,
      mode: GameMode.Solo,
      outcome,
      score,
      finishReason: input.state.finishReason,
      policyConfig,
      lineMetrics: {
        totalLinesCleared: input.state.lineClearStats.totalLinesCleared,
        maxCombo: input.state.lineClearStats.maxCombo
      }
    });
    const metadata: LineBreakerHistoryMetadata = {
      finishReason: input.state.finishReason,
      lineClearStats: input.state.lineClearStats as unknown as Record<string, unknown>,
      metrics: input.state.metrics as unknown as Record<string, unknown>,
      formulaVersion:
        typeof input.state.result?.metadata?.formulaVersion === "string"
          ? input.state.result.metadata.formulaVersion
          : "line_breaker_v1"
    };
    const inserted = await this.dependencies.repositories.history.create({
      historyId: `hist_${input.sessionId}`,
      playerId: input.player.playerId,
      sessionId: input.sessionId,
      gameType: GameType.LineBreaker,
      mode: GameMode.Solo,
      outcome,
      score,
      rewardPoints: reward.points,
      startedAt: input.startedAt,
      endedAt: input.endedAt,
      metadata: {
        ...metadata,
        loyalty: {
          points: reward.points,
          formulaVersion: reward.formulaVersion,
          breakdown: reward.breakdown
        }
      } as unknown as Record<string, unknown>
    });

    if (!inserted) {
      return;
    }

    const loyaltySummary = await this.dependencies.repositories.players.applyLoyaltyDelta({
      playerId: input.player.playerId,
      pointsDelta: reward.points
    });
    await this.dependencies.repositories.loyaltyLedger.append({
      playerId: input.player.playerId,
      sessionId: input.sessionId,
      entryType: reward.entryType,
      pointsDelta: reward.points,
      balanceAfter: loyaltySummary.balance,
      expiresAt: reward.expiresAt,
      reason: reward.reason,
      gameType: GameType.LineBreaker,
      mode: GameMode.Solo,
      outcome,
      score,
      metadata: {
        finishReason: input.state.finishReason,
        formulaVersion: reward.formulaVersion,
        breakdown: reward.breakdown,
        totalLinesCleared: input.state.lineClearStats.totalLinesCleared,
        maxCombo: input.state.lineClearStats.maxCombo
      }
    });
    await this.dependencies.repositories.players.recordCompletedSession({
      playerId: input.player.playerId,
      score,
      endedAt: input.endedAt,
      outcome
    });
  }

  private updateLineClearStats(
    current: LineBreakerPersistedState["lineClearStats"],
    linesCleared: number,
    horizontalLines: number,
    verticalLines: number,
    clearedCellCount: number
  ): LineBreakerPersistedState["lineClearStats"] {
    if (linesCleared === 0) {
      return {
        ...current,
        currentCombo: 0
      };
    }

    const comboAfterTurn = current.currentCombo + 1;
    const multiLineBonusThisTurn = linesCleared > 1 ? linesCleared * linesCleared * 10 : 0;
    const comboBonusThisTurn = comboAfterTurn > 1 ? (comboAfterTurn - 1) * 15 : 0;

    return {
      totalLinesCleared: current.totalLinesCleared + linesCleared,
      horizontalLinesCleared: current.horizontalLinesCleared + horizontalLines,
      verticalLinesCleared: current.verticalLinesCleared + verticalLines,
      simultaneousClearTurns:
        current.simultaneousClearTurns + (linesCleared > 1 ? 1 : 0),
      maxLinesSingleTurn: Math.max(current.maxLinesSingleTurn, linesCleared),
      totalClearedCells: current.totalClearedCells + clearedCellCount,
      currentCombo: comboAfterTurn,
      maxCombo: Math.max(current.maxCombo, comboAfterTurn),
      comboBonusTotal: current.comboBonusTotal + comboBonusThisTurn,
      multiLineBonusTotal: current.multiLineBonusTotal + multiLineBonusThisTurn
    };
  }

  private getShapeOrThrow(shapeId: string) {
    const shape = this.shapesById.get(shapeId);

    if (!shape) {
      throw new Error(`Unknown brick shape '${shapeId}'.`);
    }

    return shape;
  }

  private computeRemainingMs(state: LineBreakerPersistedState, referenceDate: Date): number {
    return computeRemainingTurnMs(state.turn, referenceDate);
  }

  private calculatePlacementTimeBonus(state: LineBreakerPersistedState): number {
    return calculateTurnTimeBonus({
      turn: state.turn,
      turnTimeLimitMs: state.turnTimeLimitMs,
      referenceDate: this.nowProvider(),
      maxBonus: 2
    });
  }

  private resolveEndedAt(state: LineBreakerPersistedState): Date | undefined {
    const completedAt = state.result?.metadata?.completedAt;
    return typeof completedAt === "string" ? new Date(completedAt) : undefined;
  }

  private toPublicState(
    document: GameSessionDocument,
    state: LineBreakerPersistedState
  ): LineBreakerSessionState {
    return {
      session: toGameSessionSummary(document),
      turn: state.turn,
      board: state.board,
      currentOffer: state.currentOffer,
      availablePlacementCount: state.availablePlacementCount,
      score: state.score,
      lineClearStats: state.lineClearStats,
      metrics: state.metrics,
      maxSequenceLength: state.maxSequenceLength,
      turnTimeLimitMs: state.turnTimeLimitMs,
      finished: state.finished,
      finishReason: state.finishReason,
      result: state.result
    };
  }
}
