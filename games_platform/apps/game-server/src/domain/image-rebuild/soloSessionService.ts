import { randomUUID } from "node:crypto";
import {
  ConnectionState,
  EventType,
  GameMode,
  GameType,
  HistoryOutcome,
  ImageRebuildSessionState,
  INITIAL_BRICK_SHAPES,
  PlayerSeatType,
  PuzzleTarget,
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
import {
  DeterministicBrickSequence,
  WeightedColorOption
} from "../common/deterministicSequence.js";
import {
  calculateTurnTimeBonus,
  computeRemainingTurnMs,
  createTimedTurnState,
  resolveSessionSeed,
  toGameSessionSummary
} from "../common/soloSessionSupport.js";
import { calculateLoyaltyReward, LoyaltyPolicyConfig } from "../common/loyalty.js";
import { fetchLoyaltyPolicy, toLoyaltyPolicyConfig } from "../common/loyaltyPolicyFetcher.js";
import {
  calculateImageRebuildMetrics,
  calculateImageRebuildScore
} from "./score.js";
import { ImageRebuildPersistedState } from "./types.js";
import { PuzzleCatalog } from "../../modules/puzzles/puzzleCatalog.js";
import {
  GameSessionDocument
} from "../../storage/mongoose/models/GameSessionModel.js";
import { RepositoryBundle } from "../../storage/mongoose/repositories/index.js";

const SOLO_RULESET_VERSION = "image_rebuild_solo_v1";

export class PuzzleNotFoundError extends Error {
  constructor(puzzleId: string) {
    super(`Unknown puzzle '${puzzleId}'.`);
    this.name = "PuzzleNotFoundError";
  }
}

export class SoloSessionNotFoundError extends Error {
  constructor(sessionId: string) {
    super(`Unknown image_rebuild solo session '${sessionId}'.`);
    this.name = "SoloSessionNotFoundError";
  }
}

function parsePersistedState(sessionId: string, value: unknown): ImageRebuildPersistedState {
  if (!value || typeof value !== "object") {
    throw new Error(`Missing persisted state for session '${sessionId}'.`);
  }

  const candidate = value as Partial<ImageRebuildPersistedState>;

  if (
    typeof candidate.puzzleId !== "string" ||
    typeof candidate.puzzleName !== "string" ||
    typeof candidate.puzzlePreviewImage !== "string" ||
    !candidate.board ||
    !candidate.turn
  ) {
    throw new Error(`Invalid persisted state for session '${sessionId}'.`);
  }

  return candidate as ImageRebuildPersistedState;
}

function buildWeightedColors(puzzle: PuzzleTarget): WeightedColorOption[] {
  const weights = new Map<string, number>();

  for (const row of puzzle.cells) {
    for (const color of row) {
      weights.set(color, (weights.get(color) ?? 0) + 1);
    }
  }

  return [...weights.entries()].map(([color, weight]) => ({
    color,
    weight
  }));
}

export class SoloImageRebuildService {
  private readonly shapesById = new Map(
    INITIAL_BRICK_SHAPES.map((shape) => [shape.shapeId, shape])
  );
  private readonly nowProvider: () => Date;

  constructor(
    private readonly dependencies: {
      config: Pick<AppConfig, "soloTurnLimitMs" | "imageRebuildMaxSequenceLength" | "phpApiUrl">;
      repositories: RepositoryBundle;
      puzzleCatalog: PuzzleCatalog;
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
    puzzleId: string;
    baseUrl: string;
    seed?: string;
  }): Promise<ImageRebuildSessionState> {
    const createdAt = this.nowProvider();
    const puzzle = this.getPuzzleOrThrow(input.puzzleId);
    const seed = resolveSessionSeed(input.seed);
    const sessionId = `ses_${randomUUID()}`;
    const sequence = this.createSequence(seed, puzzle);

    let state = this.buildInitialState({
      puzzle,
      sequence,
      createdAt
    });
    let status = SessionStatus.Active;
    let endedAt: Date | undefined;

    if (state.finished) {
      status = SessionStatus.Completed;
      endedAt = createdAt;
    }

    const sessionDocument: Omit<GameSessionDocument, "createdAt" | "updatedAt"> = {
      sessionId,
      mode: GameMode.Solo,
      gameType: GameType.ImageRebuild,
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
        gameType: GameType.ImageRebuild,
        mode: GameMode.Solo,
        puzzleId: puzzle.id,
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
      state,
      input.baseUrl
    );
  }

  async getSessionState(input: {
    playerId: string;
    sessionId: string;
    baseUrl: string;
  }): Promise<ImageRebuildSessionState> {
    const context = await this.loadSessionContext(input.sessionId, input.playerId);
    const synchronized = await this.synchronizeSessionIfNeeded(context);

    return this.toPublicState(synchronized.document, synchronized.state, input.baseUrl);
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
    state: ImageRebuildSessionState;
  }> {
    const context = await this.loadSessionContext(input.sessionId, input.player.playerId);
    const synchronized = await this.synchronizeSessionIfNeeded(context);

    if (synchronized.state.finished || !synchronized.state.currentOffer) {
      return {
        accepted: false,
        rejectionReason: "session_finished",
        state: this.toPublicState(
          synchronized.document,
          synchronized.state,
          input.baseUrl
        )
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
      const invalidState = this.refreshComputedState(
        {
          ...synchronized.state,
          invalidAttempts: synchronized.state.invalidAttempts + 1
        },
        synchronized.puzzle
      );

      const nextDocument = this.buildUpdatedDocument(
        synchronized.document,
        invalidState,
        synchronized.document.status
      );

      await this.saveDocumentState(nextDocument, invalidState);

      return {
        accepted: false,
        rejectionReason: validation.reason,
        state: this.toPublicState(nextDocument, invalidState, input.baseUrl)
      };
    }

    const placementTimeBonus = this.calculatePlacementTimeBonus(synchronized.state);
    const nextBoard = applyPlacement({
      board: hydrateBoardState(synchronized.state.board),
      shape,
      offer: synchronized.state.currentOffer,
      rotation: input.rotation,
      anchorX: input.anchorX,
      anchorY: input.anchorY,
      turn: synchronized.state.turn.index
    });

    let nextState = this.refreshComputedState(
      {
        ...synchronized.state,
        board: serializeBoard(nextBoard),
        accumulatedTimeBonus: synchronized.state.accumulatedTimeBonus + placementTimeBonus,
        eventSeq: synchronized.state.eventSeq + 1
      },
      synchronized.puzzle
    );

    const placementEventSeq = nextState.eventSeq;
    const now = this.nowProvider();

    await this.dependencies.repositories.gameEvents.append({
      sessionId: synchronized.document.sessionId,
      seq: placementEventSeq,
      type: EventType.BrickPlaced,
      actorPlayerId: input.player.playerId,
      payload: {
        offerId: synchronized.state.currentOffer.offerId,
        shapeId: synchronized.state.currentOffer.shapeId,
        color: synchronized.state.currentOffer.color,
        rotation: input.rotation,
        anchorX: input.anchorX,
        anchorY: input.anchorY,
        turn: synchronized.state.turn.index
      }
    });

    nextState = this.advanceAfterResolvedTurn(nextState, synchronized.puzzle, synchronized.sequence, now);
    let nextStatus = synchronized.document.status;

    if (nextState.finished) {
      nextStatus =
        nextState.finishReason === "abandoned"
          ? SessionStatus.Abandoned
          : SessionStatus.Completed;

      nextState = this.refreshComputedState(nextState, synchronized.puzzle);
      nextState = this.finalizeState(
        {
          ...nextState,
          eventSeq: nextState.eventSeq + 1
        },
        synchronized.puzzle,
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
      state: this.toPublicState(nextDocument, nextState, input.baseUrl)
    };
  }

  async abandonSession(input: {
    player: TechnicalPlayerIdentity;
    sessionId: string;
    baseUrl: string;
  }): Promise<ImageRebuildSessionState> {
    const context = await this.loadSessionContext(input.sessionId, input.player.playerId);
    const synchronized = await this.synchronizeSessionIfNeeded(context);

    if (synchronized.state.finished) {
      return this.toPublicState(synchronized.document, synchronized.state, input.baseUrl);
    }

    const endedAt = this.nowProvider();
    const abandonedState = this.finalizeState(
      {
        ...synchronized.state,
        eventSeq: synchronized.state.eventSeq + 1
      },
      synchronized.puzzle,
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

    return this.toPublicState(nextDocument, abandonedState, input.baseUrl);
  }

  async getResult(input: {
    playerId: string;
    sessionId: string;
    baseUrl: string;
  }): Promise<ImageRebuildSessionState> {
    return this.getSessionState(input);
  }

  private async loadSessionContext(sessionId: string, playerId: string): Promise<{
    document: GameSessionDocument;
    state: ImageRebuildPersistedState;
    puzzle: PuzzleTarget;
    sequence: DeterministicBrickSequence;
  }> {
    const document = await this.dependencies.repositories.gameSessions.findOwnedSessionDocument(
      sessionId,
      playerId
    );

    if (
      !document ||
      document.mode !== GameMode.Solo ||
      document.gameType !== GameType.ImageRebuild
    ) {
      throw new SoloSessionNotFoundError(sessionId);
    }

    const state = parsePersistedState(document.sessionId, document.sessionState);
    const puzzle = this.getPuzzleOrThrow(state.puzzleId);
    const sequence = this.createSequence(document.seed, puzzle);

    return {
      document,
      state,
      puzzle,
      sequence
    };
  }

  private async synchronizeSessionIfNeeded(context: {
    document: GameSessionDocument;
    state: ImageRebuildPersistedState;
    puzzle: PuzzleTarget;
    sequence: DeterministicBrickSequence;
  }): Promise<{
    document: GameSessionDocument;
    state: ImageRebuildPersistedState;
    puzzle: PuzzleTarget;
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
      nextState = this.refreshComputedState(
        {
          ...nextState,
          turn: {
            ...nextState.turn,
            skippedOffers: nextState.turn.skippedOffers + 1,
            expiredTurns: nextState.turn.expiredTurns + 1
          }
        },
        context.puzzle
      );
      nextState = this.advanceAfterResolvedTurn(
        nextState,
        context.puzzle,
        context.sequence,
        new Date(deadlineMs)
      );

      if (nextState.finished) {
        nextState = this.finalizeState(
          {
            ...nextState,
            eventSeq: nextState.eventSeq + 1
          },
          context.puzzle,
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

  private getPuzzleOrThrow(puzzleId: string): PuzzleTarget {
    const puzzle = this.dependencies.puzzleCatalog.getTarget(puzzleId);

    if (!puzzle) {
      throw new PuzzleNotFoundError(puzzleId);
    }

    return puzzle;
  }

  private getShapeOrThrow(shapeId: string) {
    const shape = this.shapesById.get(shapeId);

    if (!shape) {
      throw new Error(`Unknown brick shape '${shapeId}'.`);
    }

    return shape;
  }

  private createSequence(seed: string, puzzle: PuzzleTarget): DeterministicBrickSequence {
    return new DeterministicBrickSequence(
      `${seed}:${puzzle.id}:${SOLO_RULESET_VERSION}`,
      INITIAL_BRICK_SHAPES,
      buildWeightedColors(puzzle)
    );
  }

  private buildInitialState(input: {
    puzzle: PuzzleTarget;
    sequence: DeterministicBrickSequence;
    createdAt: Date;
  }): ImageRebuildPersistedState {
    const board = serializeBoard(
      createEmptyBoard(input.puzzle.gridWidth, input.puzzle.gridHeight)
    );
    const currentOffer =
      this.dependencies.config.imageRebuildMaxSequenceLength > 0
        ? input.sequence.getOffer(0)
        : null;

    let state = this.refreshComputedState(
      {
        puzzleId: input.puzzle.id,
        puzzleName: input.puzzle.name,
        puzzlePreviewImage: input.puzzle.previewImage,
        board,
        turn: createTimedTurnState(
          1,
          this.dependencies.config.soloTurnLimitMs,
          input.createdAt.getTime()
        ),
        currentOffer,
        availablePlacementCount: 0,
        score: {
          score: 0,
          accuracy: 0,
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
        metrics: {
          totalCells: input.puzzle.gridWidth * input.puzzle.gridHeight,
          occupiedCells: 0,
          matchedCells: 0,
          mismatchedCells: 0,
          emptyCells: input.puzzle.gridWidth * input.puzzle.gridHeight,
          coverageRatio: 0,
          accuracyRatio: 0,
          invalidAttempts: 0,
          expiredTurns: 0
        },
        maxSequenceLength: this.dependencies.config.imageRebuildMaxSequenceLength,
        turnTimeLimitMs: this.dependencies.config.soloTurnLimitMs,
        finished: false,
        invalidAttempts: 0,
        accumulatedTimeBonus: 0,
        eventSeq: 2
      },
      input.puzzle
    );

    if (!state.currentOffer) {
      return this.finalizeState(
        {
          ...state,
          eventSeq: 3
        },
        input.puzzle,
        "sequence_exhausted",
        input.createdAt
      );
    }

    if (state.availablePlacementCount === 0) {
      return this.finalizeState(
        {
          ...state,
          eventSeq: 3
        },
        input.puzzle,
        "no_more_moves",
        input.createdAt
      );
    }

    return state;
  }

  private refreshComputedState(
    state: ImageRebuildPersistedState,
    puzzle: PuzzleTarget
  ): ImageRebuildPersistedState {
    const availablePlacementCount = state.currentOffer
      ? listValidPlacementsForShape(
          hydrateBoardState(state.board),
          this.getShapeOrThrow(state.currentOffer.shapeId)
        ).length
      : 0;
    const metrics = calculateImageRebuildMetrics(state.board, puzzle, {
      invalidAttempts: state.invalidAttempts,
      expiredTurns: state.turn.expiredTurns
    });
    const score = calculateImageRebuildScore(metrics, {
      invalidAttempts: state.invalidAttempts,
      expiredTurns: state.turn.expiredTurns,
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
    state: ImageRebuildPersistedState,
    puzzle: PuzzleTarget,
    sequence: DeterministicBrickSequence,
    baseTime: Date
  ): ImageRebuildPersistedState {
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
          startedAt: state.turn.startedAt,
          deadlineAt: undefined
        }
      };
    }

    const nextTurnIndex = state.turn.index + 1;
    const nextState = this.refreshComputedState(
      {
        ...state,
        currentOffer: sequence.getOffer(nextTurnIndex - 1),
        turn: createTimedTurnState(nextTurnIndex, state.turnTimeLimitMs, baseTime.getTime(), {
          skippedOffers: state.turn.skippedOffers,
          expiredTurns: state.turn.expiredTurns
        }),
        finished: false,
        finishReason: undefined,
        result: undefined
      },
      puzzle
    );

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
    state: ImageRebuildPersistedState,
    puzzle: PuzzleTarget,
    reason: "no_more_moves" | "sequence_exhausted" | "abandoned",
    endedAt: Date
  ): ImageRebuildPersistedState {
    const refreshed = this.refreshComputedState(
      {
        ...state,
        finished: true,
        finishReason: reason,
        currentOffer: null,
        availablePlacementCount: 0,
        turn: {
          ...state.turn,
          deadlineAt: undefined
        }
      },
      puzzle
    );

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
    state: ImageRebuildPersistedState,
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
    state: ImageRebuildPersistedState
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
    state: ImageRebuildPersistedState
  ): Promise<void> {
    await this.dependencies.repositories.gameEvents.append({
      sessionId,
      seq,
      type: EventType.SessionFinished,
      actorPlayerId,
      payload: {
        finishReason: state.finishReason,
        score: state.result?.score ?? state.score.score,
        matchedCells: state.metrics.matchedCells,
        mismatchedCells: state.metrics.mismatchedCells,
        occupiedCells: state.metrics.occupiedCells
      }
    });
  }

  private async persistHistoryEntry(input: {
    sessionId: string;
    player: TechnicalPlayerIdentity;
    status: SessionStatus;
    startedAt: Date;
    endedAt: Date;
    state: ImageRebuildPersistedState;
  }): Promise<void> {
    const score = input.state.result?.score ?? input.state.score.score;
    const outcome: HistoryOutcome =
      input.status === SessionStatus.Abandoned ? "abandoned" : "solo";
    const policyConfig = await this.getLoyaltyPolicyConfig();
    const reward = calculateLoyaltyReward({
      gameType: GameType.ImageRebuild,
      mode: GameMode.Solo,
      outcome,
      score,
      finishReason: input.state.finishReason,
      policyConfig,
      imageMetrics: {
        matchedCells: input.state.metrics.matchedCells,
        accuracyRatio: input.state.metrics.accuracyRatio,
        coverageRatio: input.state.metrics.coverageRatio
      }
    });

    const inserted = await this.dependencies.repositories.history.create({
      historyId: `hist_${input.sessionId}`,
      playerId: input.player.playerId,
      sessionId: input.sessionId,
      gameType: GameType.ImageRebuild,
      mode: GameMode.Solo,
      outcome,
      score,
      rewardPoints: reward.points,
      startedAt: input.startedAt,
      endedAt: input.endedAt,
      metadata: {
        puzzleId: input.state.puzzleId,
        puzzleName: input.state.puzzleName,
        finishReason: input.state.finishReason,
        metrics: input.state.metrics,
        formulaVersion: input.state.result?.metadata?.formulaVersion ?? "image_rebuild_v1",
        loyalty: {
          points: reward.points,
          formulaVersion: reward.formulaVersion,
          breakdown: reward.breakdown
        }
      }
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
      gameType: GameType.ImageRebuild,
      mode: GameMode.Solo,
      outcome,
      score,
      metadata: {
        finishReason: input.state.finishReason,
        formulaVersion: reward.formulaVersion,
        breakdown: reward.breakdown,
        puzzleId: input.state.puzzleId,
        matchedCells: input.state.metrics.matchedCells,
        accuracyRatio: input.state.metrics.accuracyRatio,
        coverageRatio: input.state.metrics.coverageRatio
      }
    });
    await this.dependencies.repositories.players.recordCompletedSession({
      playerId: input.player.playerId,
      score,
      endedAt: input.endedAt,
      outcome
    });
  }

  private computeRemainingMs(state: ImageRebuildPersistedState, referenceDate: Date): number {
    return computeRemainingTurnMs(state.turn, referenceDate);
  }

  private calculatePlacementTimeBonus(state: ImageRebuildPersistedState): number {
    return calculateTurnTimeBonus({
      turn: state.turn,
      turnTimeLimitMs: state.turnTimeLimitMs,
      referenceDate: this.nowProvider(),
      maxBonus: 3
    });
  }

  private resolveEndedAt(state: ImageRebuildPersistedState): Date | undefined {
    const completedAt = state.result?.metadata?.completedAt;
    return typeof completedAt === "string" ? new Date(completedAt) : undefined;
  }

  private toPublicState(
    document: GameSessionDocument,
    state: ImageRebuildPersistedState,
    baseUrl: string
  ): ImageRebuildSessionState {
    return {
      session: toGameSessionSummary(document),
      puzzleId: state.puzzleId,
      puzzleName: state.puzzleName,
      puzzlePreviewUrl: this.dependencies.puzzleCatalog.buildPreviewUrl(
        state.puzzlePreviewImage,
        baseUrl
      ),
      turn: state.turn,
      board: state.board,
      currentOffer: state.currentOffer,
      availablePlacementCount: state.availablePlacementCount,
      score: state.score,
      metrics: state.metrics,
      maxSequenceLength: state.maxSequenceLength,
      turnTimeLimitMs: state.turnTimeLimitMs,
      finished: state.finished,
      finishReason: state.finishReason,
      result: state.result
    };
  }
}
