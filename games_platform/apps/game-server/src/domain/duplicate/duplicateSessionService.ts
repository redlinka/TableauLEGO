import { createHash, randomUUID } from "node:crypto";
import {
  AuthSource,
  BoardState,
  ConnectionState,
  DuplicateMatchResult,
  DuplicateOutcome,
  DuplicateParticipantFinishReason,
  DuplicateRoomState,
  EventType,
  GameMode,
  GameType,
  HistoryOutcome,
  INITIAL_BRICK_SHAPES,
  LINE_BREAKER_COLOR_POOL,
  PlayerSeatType,
  PuzzleTarget,
  ScoreResult,
  SessionStatus,
  TechnicalPlayerIdentity
} from "@games-platform/game-contracts";
import { AppConfig } from "../../config/env.js";
import {
  applyPlacement,
  createEmptyBoard,
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
} from "../image-rebuild/score.js";
import {
  clearCompletedLines,
  detectCompletedMonochromeLines
} from "../line-breaker/lineRules.js";
import { calculateLineBreakerScore } from "../line-breaker/score.js";
import {
  DuplicateImageRebuildParticipantStateInternal,
  DuplicateLineBreakerParticipantStateInternal,
  DuplicateParticipantStateInternal,
  DuplicateSessionStateInternal
} from "./types.js";
import { generateRoomCode } from "./roomCode.js";
import { PuzzleCatalog } from "../../modules/puzzles/puzzleCatalog.js";
import { GameSessionDocument } from "../../storage/mongoose/models/GameSessionModel.js";
import { RepositoryBundle } from "../../storage/mongoose/repositories/index.js";

const IMAGE_DUPLICATE_RULESET_VERSION = "image_rebuild_duplicate_v1";
const LINE_DUPLICATE_RULESET_VERSION = "line_breaker_duplicate_v1";

type DuplicateRealtimeNotifier = {
  broadcastToSession: (sessionId: string, message: unknown) => void;
  sendToPlayer: (playerId: string, message: unknown) => void;
};

type RoomBaseInput = {
  player: TechnicalPlayerIdentity;
  baseUrl: string;
};

export class DuplicateSessionError extends Error {
  constructor(
    readonly code: string,
    message: string
  ) {
    super(message);
    this.name = "DuplicateSessionError";
  }
}

function hashResumeToken(value: string): string {
  return createHash("sha256").update(value).digest("hex");
}

function buildWeightedColorsForPuzzle(puzzle: PuzzleTarget): WeightedColorOption[] {
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

function buildTargetBoard(puzzle: PuzzleTarget): BoardState {
  return {
    width: puzzle.gridWidth,
    height: puzzle.gridHeight,
    occupiedCount: puzzle.gridWidth * puzzle.gridHeight,
    cells: puzzle.cells.map((row) => [...row])
  };
}

function cloneScore(score: ScoreResult): ScoreResult {
  return JSON.parse(JSON.stringify(score)) as ScoreResult;
}

function normalizeChatContent(value: string): string {
  return value.trim().replace(/\s+/g, " ");
}

function parseDuplicateState(
  sessionId: string,
  value: unknown
): DuplicateSessionStateInternal {
  if (!value || typeof value !== "object") {
    throw new Error(`Missing duplicate session state for '${sessionId}'.`);
  }

  const candidate = value as Partial<DuplicateSessionStateInternal>;

  if (
    !candidate.turn ||
    typeof candidate.maxSequenceLength !== "number" ||
    !Array.isArray(candidate.players) ||
    typeof candidate.eventSeq !== "number"
  ) {
    throw new Error(`Invalid duplicate session state for '${sessionId}'.`);
  }

  return candidate as DuplicateSessionStateInternal;
}

export class DuplicateSessionService {
  private readonly shapesById = new Map(
    INITIAL_BRICK_SHAPES.map((shape) => [shape.shapeId, shape])
  );
  private readonly nowProvider: () => Date;
  private readonly sessionLocks = new Map<string, Promise<void>>();
  private readonly turnTimers = new Map<string, ReturnType<typeof setTimeout>>();
  private readonly reconnectTimers = new Map<string, ReturnType<typeof setTimeout>>();
  private notifier?: DuplicateRealtimeNotifier;
  private stopped = false;

  constructor(
    private readonly dependencies: {
      config: Pick<
        AppConfig,
        | "duplicateTurnLimitMs"
        | "duplicateReconnectGraceMs"
        | "duplicateChatMaxLength"
        | "imageRebuildMaxSequenceLength"
        | "lineBreakerMaxSequenceLength"
        | "phpApiUrl"
      >;
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

  setNotifier(notifier: DuplicateRealtimeNotifier): void {
    this.notifier = notifier;
  }

  async close(): Promise<void> {
    this.stopped = true;

    for (const timer of this.turnTimers.values()) {
      clearTimeout(timer);
    }

    for (const timer of this.reconnectTimers.values()) {
      clearTimeout(timer);
    }

    this.turnTimers.clear();
    this.reconnectTimers.clear();
  }

  async createRoom(
    input: RoomBaseInput & {
      gameType: GameType;
      puzzleId?: string;
      seed?: string;
    }
  ): Promise<{
    state: DuplicateRoomState;
    resumeToken: string;
  }> {
    const createdAt = this.nowProvider();
    const sessionId = `ses_${randomUUID()}`;
    const roomCode = await this.generateUniqueRoomCode();
    const seed = resolveSessionSeed(input.seed);
    const resumeToken = randomUUID();
    const rulesetVersion =
      input.gameType === GameType.ImageRebuild
        ? IMAGE_DUPLICATE_RULESET_VERSION
        : LINE_DUPLICATE_RULESET_VERSION;
    const playerSeat = {
      seat: PlayerSeatType.Host,
      playerId: input.player.playerId,
      publicAlias: input.player.publicAlias,
      connectionState: ConnectionState.Connected,
      ready: false,
      score: 0,
      resumeTokenHash: hashResumeToken(resumeToken)
    };

    let sessionState: DuplicateSessionStateInternal;

    if (input.gameType === GameType.ImageRebuild) {
      if (!input.puzzleId) {
        throw new DuplicateSessionError(
          "PUZZLE_REQUIRED",
          "A puzzleId is required for image_rebuild duplicate rooms."
        );
      }

      const puzzle = this.getPuzzleOrThrow(input.puzzleId);
      sessionState = {
        gameType: GameType.ImageRebuild,
        phase: "waiting",
        puzzleId: puzzle.id,
        puzzleName: puzzle.name,
        puzzlePreviewImage: puzzle.previewImage,
        targetBoard: buildTargetBoard(puzzle),
        paletteColors: puzzle.paletteColors ? { ...puzzle.paletteColors } : undefined,
        players: [this.createImagePlayerState(puzzle, input.player.playerId)],
        currentOffer: null,
        turn: {
          ...createTimedTurnState(0, this.dependencies.config.duplicateTurnLimitMs, createdAt.getTime()),
          awaitingPlayerIds: [],
          lockedPlayerIds: [],
          resolvedPlayerIds: []
        },
        maxSequenceLength: this.dependencies.config.imageRebuildMaxSequenceLength,
        turnTimeLimitMs: this.dependencies.config.duplicateTurnLimitMs,
        reconnectGraceMs: this.dependencies.config.duplicateReconnectGraceMs,
        eventSeq: 1
      };
    } else {
      sessionState = {
        gameType: GameType.LineBreaker,
        phase: "waiting",
        players: [this.createLineBreakerPlayerState(input.player.playerId)],
        currentOffer: null,
        turn: {
          ...createTimedTurnState(0, this.dependencies.config.duplicateTurnLimitMs, createdAt.getTime()),
          awaitingPlayerIds: [],
          lockedPlayerIds: [],
          resolvedPlayerIds: []
        },
        maxSequenceLength: this.dependencies.config.lineBreakerMaxSequenceLength,
        turnTimeLimitMs: this.dependencies.config.duplicateTurnLimitMs,
        reconnectGraceMs: this.dependencies.config.duplicateReconnectGraceMs,
        eventSeq: 1
      };
    }

    const document: Omit<GameSessionDocument, "createdAt" | "updatedAt"> = {
      sessionId,
      mode: GameMode.Duplicate2P,
      gameType: input.gameType,
      status: SessionStatus.Waiting,
      rulesetVersion,
      seed,
      roomCode,
      hostPlayerId: input.player.playerId,
      players: [playerSeat],
      currentTurn: 0,
      sessionState,
      sharedClock: {
        durationMs: this.dependencies.config.duplicateTurnLimitMs,
        remainingMs: 0,
        tickRateMs: 1000
      },
      startedAt: undefined,
      endedAt: undefined
    };

    await this.dependencies.repositories.gameSessions.create(document);
    await this.dependencies.repositories.gameEvents.append({
      sessionId,
      seq: 1,
      type: EventType.RoomCreated,
      actorPlayerId: input.player.playerId,
      payload: {
        roomCode,
        gameType: input.gameType,
        mode: GameMode.Duplicate2P,
        puzzleId:
          sessionState.gameType === GameType.ImageRebuild ? sessionState.puzzleId : undefined
      }
    });

    const publicState = await this.toPublicState(
      {
        ...document,
        createdAt,
        updatedAt: createdAt
      },
      sessionState,
      input.baseUrl
    );

    return {
      state: publicState,
      resumeToken
    };
  }

  async joinRoom(
    input: RoomBaseInput & {
      roomCode: string;
    }
  ): Promise<{
    state: DuplicateRoomState;
    resumeToken: string;
    joinedPlayer: TechnicalPlayerIdentity;
  }> {
    const roomCode = input.roomCode.trim().toUpperCase();
    const found = await this.dependencies.repositories.gameSessions.findDocumentByRoomCode(roomCode);

    if (!found || found.mode !== GameMode.Duplicate2P) {
      throw new DuplicateSessionError("ROOM_NOT_FOUND", `Unknown room '${roomCode}'.`);
    }

    return this.runLocked(found.sessionId, async () => {
      const document = await this.requireDuplicateDocument(found.sessionId);
      let state = parseDuplicateState(document.sessionId, document.sessionState);

      if (state.phase === "in_game" || state.phase === "finished" || state.phase === "cancelled") {
        throw new DuplicateSessionError(
          "ROOM_UNAVAILABLE",
          "This room is no longer joinable."
        );
      }

      const existingSeat = document.players.find((player) => player.playerId === input.player.playerId);

      if (!existingSeat && document.players.length >= 2) {
        throw new DuplicateSessionError("ROOM_FULL", "This room already has two players.");
      }

      const resumeToken = randomUUID();
      const joinedPlayer = input.player;

      if (existingSeat) {
        existingSeat.connectionState = ConnectionState.Connected;
        existingSeat.publicAlias = input.player.publicAlias;
        existingSeat.resumeTokenHash = hashResumeToken(resumeToken);
        existingSeat.ready = existingSeat.seat === PlayerSeatType.Host ? existingSeat.ready : false;
        const playerState = this.getParticipantState(state, input.player.playerId);
        playerState.disconnectDeadlineAt = undefined;
      } else {
        document.players.push({
          seat: PlayerSeatType.Guest,
          playerId: input.player.playerId,
          publicAlias: input.player.publicAlias,
          connectionState: ConnectionState.Connected,
          ready: false,
          score: 0,
          resumeTokenHash: hashResumeToken(resumeToken)
        });
        state = this.addPlayerState(state, input.player.playerId);
      }

      state.phase = this.computeLobbyPhase(document);
      document.status = SessionStatus.Waiting;
      document.sessionState = state;
      document.currentTurn = state.turn.index;
      document.sharedClock = {
        ...document.sharedClock,
        remainingMs: 0
      };

      await this.dependencies.repositories.gameSessions.saveDocument(document);
      state = parseDuplicateState(document.sessionId, document.sessionState);

      state.eventSeq += 1;
      document.sessionState = state;
      await this.dependencies.repositories.gameSessions.saveDocument(document);
      await this.dependencies.repositories.gameEvents.append({
        sessionId: document.sessionId,
        seq: state.eventSeq,
        type: EventType.RoomJoined,
        actorPlayerId: input.player.playerId,
        payload: {
          roomCode: document.roomCode,
          playerId: input.player.playerId
        }
      });

      const publicState = await this.toPublicState(document, state, input.baseUrl);

      return {
        state: publicState,
        resumeToken,
        joinedPlayer
      };
    });
  }

  async setReady(
    input: RoomBaseInput & {
      sessionId: string;
      ready: boolean;
    }
  ): Promise<DuplicateRoomState> {
    return this.runLocked(input.sessionId, async () => {
      const document = await this.requireDuplicateDocument(input.sessionId);
      const seat = this.getSeatOrThrow(document, input.player.playerId);
      const state = parseDuplicateState(document.sessionId, document.sessionState);

      if (state.phase !== "waiting" && state.phase !== "ready") {
        throw new DuplicateSessionError(
          "ROOM_NOT_WAITING",
          "Ready state can only be changed before the game starts."
        );
      }

      seat.ready = input.ready;
      document.status = SessionStatus.Waiting;
      state.phase = this.computeLobbyPhase(document);
      state.eventSeq += 1;
      document.sessionState = state;

      await this.dependencies.repositories.gameSessions.saveDocument(document);
      await this.dependencies.repositories.gameEvents.append({
        sessionId: document.sessionId,
        seq: state.eventSeq,
        type: EventType.ReadyChanged,
        actorPlayerId: input.player.playerId,
        payload: {
          ready: input.ready
        }
      });

      return this.toPublicState(document, state, input.baseUrl);
    });
  }

  async startRoom(input: RoomBaseInput & { sessionId: string }): Promise<DuplicateRoomState> {
    return this.runLocked(input.sessionId, async () => {
      const document = await this.requireDuplicateDocument(input.sessionId);
      let state = parseDuplicateState(document.sessionId, document.sessionState);

      if (document.hostPlayerId !== input.player.playerId) {
        throw new DuplicateSessionError("HOST_ONLY", "Only the host can start the room.");
      }

      if (state.phase !== "ready") {
        throw new DuplicateSessionError(
          "ROOM_NOT_READY",
          "The room must have two ready players before starting."
        );
      }

      if (document.players.length !== 2 || !document.players.every((player) => player.ready)) {
        throw new DuplicateSessionError(
          "PLAYERS_NOT_READY",
          "Both players must be present and ready."
        );
      }

      const now = this.nowProvider();
      state = this.startNextTurn(state, document, now);
      document.status = state.phase === "finished" ? SessionStatus.Completed : SessionStatus.Active;
      document.startedAt = now;
      document.currentTurn = state.turn.index;
      document.sessionState = state;
      document.sharedClock = this.buildSharedClock(state, now, document.startedAt);

      state.eventSeq += 1;
      document.sessionState = state;
      await this.dependencies.repositories.gameSessions.saveDocument(document);
      await this.dependencies.repositories.gameEvents.append({
        sessionId: document.sessionId,
        seq: state.eventSeq,
        type: EventType.SessionStarted,
        actorPlayerId: input.player.playerId,
        payload: {
          turn: state.turn.index,
          offerId: state.currentOffer?.offerId ?? null
        }
      });

      const publicState = await this.toPublicState(document, state, input.baseUrl);
      this.scheduleTurnTimer(document.sessionId, state, input.baseUrl);

      if (state.phase === "finished") {
        await this.persistDuplicateHistory(document, state);
      }

      return publicState;
    });
  }

  async leaveRoom(
    input: RoomBaseInput & {
      sessionId: string;
    }
  ): Promise<DuplicateRoomState> {
    return this.runLocked(input.sessionId, async () => {
      const document = await this.requireDuplicateDocument(input.sessionId);
      let state = parseDuplicateState(document.sessionId, document.sessionState);
      const seat = this.getSeatOrThrow(document, input.player.playerId);

      if (state.phase === "waiting" || state.phase === "ready") {
        if (seat.seat === PlayerSeatType.Host) {
          state.phase = "cancelled";
          state.result = {
            finishReason: "cancelled",
            players: document.players.map((player) => ({
              playerId: player.playerId,
              publicAlias: player.publicAlias,
              outcome: "draw",
              score: 0,
              finishReason: "cancelled"
            })),
            endedAt: this.nowProvider().toISOString()
          };
          document.status = SessionStatus.Abandoned;
          document.endedAt = this.nowProvider();
        } else {
          document.players = document.players.filter((player) => player.playerId !== input.player.playerId);
          state = this.removePlayerState(state, input.player.playerId);
          document.status = SessionStatus.Waiting;
          state.phase = "waiting";
          document.players.forEach((player) => {
            player.ready = false;
          });
        }
      } else if (state.phase === "in_game") {
        const finished = this.finishByForfeit(state, document, input.player.playerId, "left");
        state = finished.state;
        document.status = SessionStatus.Completed;
        document.endedAt = finished.endedAt;
      }

      state.eventSeq += 1;
      document.currentTurn = state.turn.index;
      document.sessionState = state;
      document.sharedClock = this.buildSharedClock(state, this.nowProvider(), document.startedAt);

      await this.dependencies.repositories.gameSessions.saveDocument(document);
      await this.dependencies.repositories.gameEvents.append({
        sessionId: document.sessionId,
        seq: state.eventSeq,
        type: EventType.RoomLeft,
        actorPlayerId: input.player.playerId,
        payload: {
          playerId: input.player.playerId,
          phase: state.phase
        }
      });

      if (state.phase === "finished") {
        await this.persistDuplicateHistory(document, state);
      }

      return this.toPublicState(document, state, input.baseUrl);
    });
  }

  async submitPlacement(
    input: RoomBaseInput & {
      sessionId: string;
      offerId: string;
      anchorX: number;
      anchorY: number;
      rotation: number;
    }
  ): Promise<{
    accepted: boolean;
    rejectionReason?: string;
    state: DuplicateRoomState;
  }> {
    return this.runLocked(input.sessionId, async () => {
      const document = await this.requireDuplicateDocument(input.sessionId);
      let state = parseDuplicateState(document.sessionId, document.sessionState);
      state = await this.synchronizeIfNeeded(document, state, input.baseUrl);

      if (state.phase !== "in_game" || !state.currentOffer) {
        return {
          accepted: false,
          rejectionReason: "session_not_active",
          state: await this.toPublicState(document, state, input.baseUrl)
        };
      }

      if (state.currentOffer.offerId !== input.offerId) {
        return {
          accepted: false,
          rejectionReason: "offer_mismatch",
          state: await this.toPublicState(document, state, input.baseUrl)
        };
      }

      const playerState = this.getParticipantState(state, input.player.playerId);

      if (playerState.finished) {
        return {
          accepted: false,
          rejectionReason: "player_finished",
          state: await this.toPublicState(document, state, input.baseUrl)
        };
      }

      if (playerState.turnStatus !== "pending") {
        return {
          accepted: false,
          rejectionReason: "turn_already_locked",
          state: await this.toPublicState(document, state, input.baseUrl)
        };
      }

      const shape = this.getShapeOrThrow(state.currentOffer.shapeId);
      const validation = validatePlacement({
        board: hydrateBoardState(playerState.board),
        shape,
        rotation: input.rotation,
        anchorX: input.anchorX,
        anchorY: input.anchorY
      });

      if (!validation.valid) {
        state = this.incrementInvalidAttempt(state, input.player.playerId);
        document.sessionState = state;
        document.sharedClock = this.buildSharedClock(state, this.nowProvider(), document.startedAt);
        this.syncDocumentScores(document, state);
        await this.dependencies.repositories.gameSessions.saveDocument(document);

        return {
          accepted: false,
          rejectionReason: validation.reason,
          state: await this.toPublicState(document, state, input.baseUrl)
        };
      }

      playerState.pendingPlacement = {
        offerId: input.offerId,
        anchorX: input.anchorX,
        anchorY: input.anchorY,
        rotation: input.rotation,
        submittedAt: this.nowProvider().toISOString()
      };
      playerState.turnStatus = "locked";
      state.turn.awaitingPlayerIds = state.turn.awaitingPlayerIds.filter(
        (playerId) => playerId !== input.player.playerId
      );
      if (!state.turn.lockedPlayerIds.includes(input.player.playerId)) {
        state.turn.lockedPlayerIds.push(input.player.playerId);
      }

      document.sessionState = state;
      document.sharedClock = this.buildSharedClock(state, this.nowProvider(), document.startedAt);
      this.syncDocumentScores(document, state);
      await this.dependencies.repositories.gameSessions.saveDocument(document);

      if (state.turn.awaitingPlayerIds.length === 0) {
        state = await this.resolveTurn(document, state, this.nowProvider(), input.baseUrl);
      } else {
        const publicState = await this.toPublicState(document, state, input.baseUrl);
        this.emitToSession(document.sessionId, {
          type: "game.state",
          serverTime: Date.now(),
          payload: {
            state: publicState
          }
        });
      }

      return {
        accepted: true,
        state: await this.toPublicState(document, state, input.baseUrl)
      };
    });
  }

  async sendChat(
    input: RoomBaseInput & {
      sessionId: string;
      content: string;
    }
  ): Promise<DuplicateRoomState> {
    const content = normalizeChatContent(input.content);

    if (!content) {
      throw new DuplicateSessionError("CHAT_EMPTY", "Chat messages cannot be empty.");
    }

    if (content.length > this.dependencies.config.duplicateChatMaxLength) {
      throw new DuplicateSessionError(
        "CHAT_TOO_LONG",
        `Chat messages are limited to ${this.dependencies.config.duplicateChatMaxLength} characters.`
      );
    }

    return this.runLocked(input.sessionId, async () => {
      const document = await this.requireDuplicateDocument(input.sessionId);
      const state = parseDuplicateState(document.sessionId, document.sessionState);

      this.getSeatOrThrow(document, input.player.playerId);

      await this.dependencies.repositories.chatMessages.append({
        sessionId: document.sessionId,
        playerId: input.player.playerId,
        publicAlias: input.player.publicAlias,
        content
      });

      state.eventSeq += 1;
      document.sessionState = state;
      await this.dependencies.repositories.gameSessions.saveDocument(document);
      await this.dependencies.repositories.gameEvents.append({
        sessionId: document.sessionId,
        seq: state.eventSeq,
        type: EventType.ChatSent,
        actorPlayerId: input.player.playerId,
        payload: {
          content
        }
      });

      const messages = await this.dependencies.repositories.chatMessages.listBySessionId(
        document.sessionId,
        1
      );
      const message = messages[0];

      if (message) {
        this.emitToSession(document.sessionId, {
          type: "room.chat_message",
          serverTime: Date.now(),
          payload: {
            message
          }
        });
      }

      return this.toPublicState(document, state, input.baseUrl);
    });
  }

  async getRoomState(
    input: {
      playerId: string;
      sessionId: string;
      baseUrl: string;
    }
  ): Promise<DuplicateRoomState> {
    return this.runLocked(input.sessionId, async () => {
      const document = await this.requireDuplicateDocument(input.sessionId);
      this.getSeatOrThrow(document, input.playerId);
      const state = await this.synchronizeIfNeeded(
        document,
        parseDuplicateState(document.sessionId, document.sessionState),
        input.baseUrl
      );

      return this.toPublicState(document, state, input.baseUrl);
    });
  }

  async resumeSession(input: {
    sessionId: string;
    resumeToken: string;
    baseUrl: string;
  }): Promise<{
    player: TechnicalPlayerIdentity;
    resumeToken: string;
    state: DuplicateRoomState;
  }> {
    return this.runLocked(input.sessionId, async () => {
      const document = await this.requireDuplicateDocument(input.sessionId);
      const state = parseDuplicateState(document.sessionId, document.sessionState);
      const hashed = hashResumeToken(input.resumeToken);
      const seat = document.players.find((player) => player.resumeTokenHash === hashed);

      if (!seat) {
        throw new DuplicateSessionError("RESUME_FAILED", "Invalid session resume token.");
      }

      const nextResumeToken = randomUUID();
      seat.resumeTokenHash = hashResumeToken(nextResumeToken);
      seat.connectionState = ConnectionState.Connected;
      const playerState = this.getParticipantState(state, seat.playerId);
      playerState.disconnectDeadlineAt = undefined;

      this.clearReconnectTimer(document.sessionId, seat.playerId);

      document.sessionState = state;
      document.sharedClock = this.buildSharedClock(state, this.nowProvider(), document.startedAt);
      await this.dependencies.repositories.gameSessions.saveDocument(document);

      state.eventSeq += 1;
      document.sessionState = state;
      await this.dependencies.repositories.gameSessions.saveDocument(document);
      await this.dependencies.repositories.gameEvents.append({
        sessionId: document.sessionId,
        seq: state.eventSeq,
        type: EventType.PlayerReconnected,
        actorPlayerId: seat.playerId,
        payload: {
          playerId: seat.playerId
        }
      });

      const publicState = await this.toPublicState(document, state, input.baseUrl);
      this.emitToSession(document.sessionId, {
        type: "game.player_reconnected",
        serverTime: Date.now(),
        payload: {
          playerId: seat.playerId,
          state: publicState
        }
      });

      return {
        player: {
          playerId: seat.playerId,
          publicAlias: seat.publicAlias,
          authSource: seat.playerId.startsWith("ply_") ? AuthSource.Guest : AuthSource.Php
        },
        resumeToken: nextResumeToken,
        state: publicState
      };
    });
  }

  async handleSocketDisconnected(input: {
    sessionId: string;
    playerId: string;
    baseUrl: string;
  }): Promise<void> {
    if (this.stopped) {
      return;
    }

    await this.runLocked(input.sessionId, async () => {
      const document = await this.dependencies.repositories.gameSessions.findDocumentBySessionId(
        input.sessionId
      );

      if (!document || document.mode !== GameMode.Duplicate2P) {
        return;
      }

      const seat = document.players.find((player) => player.playerId === input.playerId);

      if (!seat) {
        return;
      }

      let state = parseDuplicateState(document.sessionId, document.sessionState);

      if (seat.connectionState === ConnectionState.Disconnected) {
        return;
      }

      seat.connectionState = ConnectionState.Disconnected;
      const deadline = new Date(
        this.nowProvider().getTime() + this.dependencies.config.duplicateReconnectGraceMs
      );
      const playerState = this.getParticipantState(state, input.playerId);
      playerState.disconnectDeadlineAt = deadline.toISOString();
      state.eventSeq += 1;
      document.sessionState = state;
      document.sharedClock = this.buildSharedClock(state, this.nowProvider(), document.startedAt);

      await this.dependencies.repositories.gameSessions.saveDocument(document);
      await this.dependencies.repositories.gameEvents.append({
        sessionId: document.sessionId,
        seq: state.eventSeq,
        type: EventType.PlayerDisconnected,
        actorPlayerId: input.playerId,
        payload: {
          playerId: input.playerId,
          deadlineAt: deadline.toISOString()
        }
      });

      const publicState = await this.toPublicState(document, state, input.baseUrl);
      this.emitToSession(document.sessionId, {
        type: "game.player_disconnected",
        serverTime: Date.now(),
        payload: {
          playerId: input.playerId,
          graceDeadlineAt: deadline.toISOString(),
          state: publicState
        }
      });

      this.scheduleReconnectTimer(document.sessionId, input.playerId, deadline, input.baseUrl);
    });
  }

  private async synchronizeIfNeeded(
    document: GameSessionDocument,
    state: DuplicateSessionStateInternal,
    baseUrl: string
  ): Promise<DuplicateSessionStateInternal> {
    let nextState = state;
    const now = this.nowProvider();

    if (nextState.phase === "in_game" && nextState.turn.deadlineAt) {
      const deadlineMs = Date.parse(nextState.turn.deadlineAt);

      if (Number.isFinite(deadlineMs) && now.getTime() >= deadlineMs) {
        nextState = await this.resolveTurn(document, nextState, new Date(deadlineMs), baseUrl);
      }
    }

    const expiredPlayers = nextState.players.filter(
      (player) =>
        player.disconnectDeadlineAt &&
        Date.parse(player.disconnectDeadlineAt) <= now.getTime()
    );

    if (expiredPlayers.length > 0) {
      for (const player of expiredPlayers) {
        nextState = await this.resolveReconnectExpiry(
          document,
          nextState,
          player.playerId,
          now,
          baseUrl
        );
      }
    }

    return nextState;
  }

  private async resolveReconnectExpiry(
    document: GameSessionDocument,
    state: DuplicateSessionStateInternal,
    playerId: string,
    endedAt: Date,
    baseUrl: string
  ): Promise<DuplicateSessionStateInternal> {
    const seatBeforeExpiry = document.players.find((player) => player.playerId === playerId);
    const playerStateBeforeExpiry = state.players.find((player) => player.playerId === playerId);

    if (
      !seatBeforeExpiry ||
      seatBeforeExpiry.connectionState !== ConnectionState.Disconnected ||
      !playerStateBeforeExpiry?.disconnectDeadlineAt ||
      Date.parse(playerStateBeforeExpiry.disconnectDeadlineAt) > endedAt.getTime()
    ) {
      return state;
    }

    if (state.phase === "waiting" || state.phase === "ready") {
      const seat = this.getSeatOrThrow(document, playerId);

      if (seat.seat === PlayerSeatType.Host) {
        state.phase = "cancelled";
        state.result = {
          finishReason: "cancelled",
          players: document.players.map((player) => ({
            playerId: player.playerId,
            publicAlias: player.publicAlias,
            outcome: "draw",
            score: 0,
            finishReason: "cancelled"
          })),
          endedAt: endedAt.toISOString()
        };
        document.status = SessionStatus.Abandoned;
        document.endedAt = endedAt;
      } else {
        document.players = document.players.filter((player) => player.playerId !== playerId);
        state = this.removePlayerState(state, playerId);
        document.players.forEach((player) => {
          player.ready = false;
        });
        state.phase = "waiting";
      }
    } else if (state.phase === "in_game") {
      const finished = this.finishByForfeit(state, document, playerId, "forfeit");
      state = finished.state;
      document.status = SessionStatus.Completed;
      document.endedAt = finished.endedAt;
      await this.persistDuplicateHistory(document, state);
    }

    this.clearReconnectTimer(document.sessionId, playerId);
    state.eventSeq += 1;
    document.sessionState = state;
    document.sharedClock = this.buildSharedClock(state, endedAt, document.startedAt);
    this.syncDocumentScores(document, state);
    await this.dependencies.repositories.gameSessions.saveDocument(document);
    await this.dependencies.repositories.gameEvents.append({
      sessionId: document.sessionId,
      seq: state.eventSeq,
      type: EventType.PlayerForfeited,
      actorPlayerId: playerId,
      payload: {
        playerId,
        phase: state.phase
      }
    });

    const publicState = await this.toPublicState(document, state, baseUrl);

    if (state.phase === "finished") {
      this.emitToSession(document.sessionId, {
        type: "game.finished",
        serverTime: Date.now(),
        payload: {
          state: publicState
        }
      });
    } else {
      this.emitToSession(document.sessionId, {
        type: "room.state",
        serverTime: Date.now(),
        payload: {
          state: publicState
        }
      });
    }

    return state;
  }

  private async resolveTurn(
    document: GameSessionDocument,
    state: DuplicateSessionStateInternal,
    resolvedAt: Date,
    baseUrl: string
  ): Promise<DuplicateSessionStateInternal> {
    if (state.phase !== "in_game" || !state.currentOffer) {
      return state;
    }

    this.clearTurnTimer(document.sessionId);

    const resolutionPlayers: DuplicateMatchResult["players"] = [];
    const offer = state.currentOffer;

    for (const playerState of state.players) {
      const seat = this.getSeatOrThrow(document, playerState.playerId);

      if (playerState.finished) {
        playerState.turnStatus = "finished";
        resolutionPlayers.push({
          playerId: seat.playerId,
          publicAlias: seat.publicAlias,
          outcome: "draw",
          score: this.getPlayerScore(playerState),
          finishReason: playerState.finishReason
        });
        continue;
      }

      if (playerState.pendingPlacement) {
        this.applyPlayerPlacement(state, playerState, offer, playerState.pendingPlacement, false);
        playerState.turnStatus = "locked";
        playerState.pendingPlacement = undefined;
        state.eventSeq += 1;
        await this.dependencies.repositories.gameEvents.append({
          sessionId: document.sessionId,
          seq: state.eventSeq,
          type: EventType.BrickPlaced,
          actorPlayerId: playerState.playerId,
          payload: {
            offerId: offer.offerId,
            turn: state.turn.index,
            source: "submission"
          }
        });
        resolutionPlayers.push({
          playerId: seat.playerId,
          publicAlias: seat.publicAlias,
          outcome: "draw",
          score: this.getPlayerScore(playerState),
          finishReason: playerState.finishReason
        });
        continue;
      }

      if (playerState.availablePlacementCount > 0) {
        this.incrementExpiredTurn(playerState);
        const autoPlacement = this.chooseAutoPlacement(state, playerState, offer);

        if (autoPlacement) {
          this.applyPlayerPlacement(state, playerState, offer, autoPlacement, true);
          playerState.turnStatus = "auto_placed";
          state.eventSeq += 1;
          await this.dependencies.repositories.gameEvents.append({
            sessionId: document.sessionId,
            seq: state.eventSeq,
            type: EventType.BrickPlaced,
            actorPlayerId: playerState.playerId,
            payload: {
              offerId: offer.offerId,
              turn: state.turn.index,
              source: "auto"
            }
          });
        } else {
          this.finishParticipant(state, playerState, "no_more_moves", resolvedAt);
        }
      } else {
        this.finishParticipant(state, playerState, "no_more_moves", resolvedAt);
      }

      resolutionPlayers.push({
        playerId: seat.playerId,
        publicAlias: seat.publicAlias,
        outcome: "draw",
        score: this.getPlayerScore(playerState),
        finishReason: playerState.finishReason
      });
    }

    state.lastTurnResolution = {
      turnIndex: state.turn.index,
      offerId: offer.offerId,
      offer,
      resolvedAt: resolvedAt.toISOString(),
      players: resolutionPlayers.map((player) => ({
        playerId: player.playerId,
        publicAlias: player.publicAlias,
        mode:
          player.finishReason === "forfeit"
            ? "forfeit"
            : player.finishReason === "no_more_moves"
              ? "skipped"
              : "submitted",
        accepted: player.finishReason !== "no_more_moves",
        scoreAfter: player.score,
        finished: Boolean(player.finishReason),
        finishReason: player.finishReason
      }))
    };
    state.eventSeq += 1;
    await this.dependencies.repositories.gameEvents.append({
      sessionId: document.sessionId,
      seq: state.eventSeq,
      type: EventType.TurnResolved,
      payload: {
        turn: state.turn.index,
        offerId: offer.offerId
      }
    });

    if (this.areAllPlayersFinished(state)) {
      state = this.finalizeCompletedMatch(state, document, "completed", resolvedAt);
    } else if ((state.currentOffer?.sequenceIndex ?? state.turn.index - 1) + 1 >= state.maxSequenceLength) {
      for (const playerState of state.players) {
        if (!playerState.finished) {
          this.finishParticipant(state, playerState, "sequence_exhausted", resolvedAt);
        }
      }
      state = this.finalizeCompletedMatch(state, document, "sequence_exhausted", resolvedAt);
    } else {
      state = this.startNextTurn(
        {
          ...state,
          currentOffer: null
        },
        document,
        resolvedAt,
        state.turn.index + 1
      );
    }

    document.status = state.phase === "finished" ? SessionStatus.Completed : SessionStatus.Active;
    document.endedAt =
      state.phase === "finished" ? new Date(state.result?.endedAt ?? resolvedAt.toISOString()) : undefined;
    document.currentTurn = state.turn.index;
    document.sessionState = state;
    document.sharedClock = this.buildSharedClock(state, resolvedAt, document.startedAt);
    this.syncDocumentScores(document, state);
    await this.dependencies.repositories.gameSessions.saveDocument(document);

    const publicState = await this.toPublicState(document, state, baseUrl);
    this.emitToSession(document.sessionId, {
      type: "game.turn_resolved",
      serverTime: Date.now(),
      payload: {
        state: publicState,
        resolution: state.lastTurnResolution
      }
    });

    if (state.phase === "finished") {
      await this.persistDuplicateHistory(document, state);
      this.emitToSession(document.sessionId, {
        type: "game.finished",
        serverTime: Date.now(),
        payload: {
          state: publicState
        }
      });
    } else {
      this.scheduleTurnTimer(document.sessionId, state, baseUrl);
      this.emitToSession(document.sessionId, {
        type: "game.turn_started",
        serverTime: Date.now(),
        payload: {
          state: publicState
        }
      });
    }

    return state;
  }

  private startNextTurn(
    state: DuplicateSessionStateInternal,
    document: GameSessionDocument,
    startedAt: Date,
    turnIndex = 1
  ): DuplicateSessionStateInternal {
    const sequence = this.createSequence(document, state);
    const currentOffer =
      turnIndex <= state.maxSequenceLength ? sequence.getOffer(turnIndex - 1) : null;

    state.phase = "in_game";
    state.currentOffer = currentOffer;
    state.turn = {
      ...createTimedTurnState(
        turnIndex,
        this.dependencies.config.duplicateTurnLimitMs,
        startedAt.getTime()
      ),
      awaitingPlayerIds: [],
      lockedPlayerIds: [],
      resolvedPlayerIds: [],
      lastResolvedAt: state.lastTurnResolution?.resolvedAt
    };

    for (const playerState of state.players) {
      playerState.pendingPlacement = undefined;

      if (playerState.finished || !currentOffer) {
        playerState.turnStatus = "finished";
        playerState.availablePlacementCount = 0;
        continue;
      }

      playerState.availablePlacementCount = this.computeAvailablePlacementCount(
        playerState.board,
        currentOffer.shapeId
      );

      if (playerState.availablePlacementCount > 0) {
        playerState.turnStatus = "pending";
        state.turn.awaitingPlayerIds.push(playerState.playerId);
      } else {
        this.finishParticipant(state, playerState, "no_more_moves", startedAt);
      }
    }

    if (!currentOffer || this.areAllPlayersFinished(state)) {
      return this.finalizeCompletedMatch(
        state,
        document,
        currentOffer ? "completed" : "sequence_exhausted",
        startedAt
      );
    }

    state.eventSeq += 1;

    return state;
  }

  private applyPlayerPlacement(
    state: DuplicateSessionStateInternal,
    playerState: DuplicateParticipantStateInternal,
    offer: {
      offerId: string;
      shapeId: string;
      color: string;
      issuedAtTurn: number;
      sequenceIndex: number;
    },
    placement: {
      anchorX: number;
      anchorY: number;
      rotation: number;
    },
    expiredPlacement: boolean
  ): void {
    const shape = this.getShapeOrThrow(offer.shapeId);
    const now = this.nowProvider();
    const timeBonus = expiredPlacement
      ? 0
      : calculateTurnTimeBonus({
          turn: state.turn,
          turnTimeLimitMs: state.turn.limitMs,
          referenceDate: now,
          maxBonus: state.gameType === GameType.ImageRebuild ? 3 : 2
        });

    const placedBoard = applyPlacement({
      board: hydrateBoardState(playerState.board),
      shape,
      offer,
      rotation: placement.rotation,
      anchorX: placement.anchorX,
      anchorY: placement.anchorY,
      turn: state.turn.index
    });

    if (state.gameType === GameType.ImageRebuild) {
      const target = this.getPuzzleOrThrow(state.puzzleId);
      const nextState = playerState as DuplicateImageRebuildParticipantStateInternal;
      nextState.board = serializeBoard(placedBoard);
      nextState.accumulatedTimeBonus += timeBonus;
      nextState.metrics = calculateImageRebuildMetrics(nextState.board, target, {
        invalidAttempts: nextState.invalidAttempts,
        expiredTurns: nextState.metrics.expiredTurns
      });
      nextState.score = calculateImageRebuildScore(nextState.metrics, {
        invalidAttempts: nextState.invalidAttempts,
        expiredTurns: nextState.metrics.expiredTurns,
        accumulatedTimeBonus: nextState.accumulatedTimeBonus
      });
      nextState.availablePlacementCount = 0;
      nextState.turnStatus = expiredPlacement ? "auto_placed" : "locked";
      return;
    }

    const nextState = playerState as DuplicateLineBreakerParticipantStateInternal;
    const completedLines = detectCompletedMonochromeLines(placedBoard);
    const cleared = clearCompletedLines({
      board: placedBoard,
      lines: completedLines
    });
    nextState.board = serializeBoard(cleared.board);
    nextState.accumulatedTimeBonus += timeBonus;
    nextState.lineClearStats = this.updateLineClearStats(
      nextState.lineClearStats,
      completedLines.length,
      completedLines.filter((line) => line.orientation === "row").length,
      completedLines.filter((line) => line.orientation === "column").length,
      cleared.clearedCellCount
    );
    nextState.metrics = {
      ...nextState.metrics,
      occupiedCells: cleared.board.occupiedCount,
      totalPlacedCells: nextState.metrics.totalPlacedCells + shape.baseCells.length,
      placementsAccepted: nextState.metrics.placementsAccepted + 1,
      lastClearedLines: completedLines.map((line) => ({
        orientation: line.orientation,
        index: line.index,
        color: line.color,
        cellCount: line.cellCount
      })),
      lastClearedCellCount: cleared.clearedCellCount
    };
    nextState.score = calculateLineBreakerScore({
      lineClearStats: nextState.lineClearStats,
      metrics: nextState.metrics,
      accumulatedTimeBonus: nextState.accumulatedTimeBonus
    });
    nextState.availablePlacementCount = 0;
    nextState.turnStatus = expiredPlacement ? "auto_placed" : "locked";
  }

  private chooseAutoPlacement(
    state: DuplicateSessionStateInternal,
    playerState: DuplicateParticipantStateInternal,
    offer: {
      offerId: string;
      shapeId: string;
      color: string;
      issuedAtTurn: number;
      sequenceIndex: number;
    }
  ): {
    anchorX: number;
    anchorY: number;
    rotation: number;
  } | null {
    const shape = this.getShapeOrThrow(offer.shapeId);
    const placements = listValidPlacementsForShape(hydrateBoardState(playerState.board), shape);

    if (placements.length === 0) {
      return null;
    }

    let best = placements[0];
    let bestScore = Number.NEGATIVE_INFINITY;
    let bestSecondary = Number.NEGATIVE_INFINITY;

    for (const candidate of placements) {
      const simulated = applyPlacement({
        board: hydrateBoardState(playerState.board),
        shape,
        offer,
        rotation: candidate.rotation,
        anchorX: candidate.anchorX,
        anchorY: candidate.anchorY,
        turn: state.turn.index
      });

      if (state.gameType === GameType.ImageRebuild) {
        const typedPlayer = playerState as DuplicateImageRebuildParticipantStateInternal;
        const puzzle = this.getPuzzleOrThrow(state.puzzleId);
        const metrics = calculateImageRebuildMetrics(serializeBoard(simulated), puzzle, {
          invalidAttempts: typedPlayer.invalidAttempts,
          expiredTurns: typedPlayer.metrics.expiredTurns
        });
        const score = calculateImageRebuildScore(metrics, {
          invalidAttempts: typedPlayer.invalidAttempts,
          expiredTurns: typedPlayer.metrics.expiredTurns,
          accumulatedTimeBonus: typedPlayer.accumulatedTimeBonus
        }).score;

        if (score > bestScore) {
          best = candidate;
          bestScore = score;
        }

        continue;
      }

      const typedPlayer = playerState as DuplicateLineBreakerParticipantStateInternal;
      const completedLines = detectCompletedMonochromeLines(simulated);
      const cleared = clearCompletedLines({
        board: simulated,
        lines: completedLines
      });
      const nextStats = this.updateLineClearStats(
        typedPlayer.lineClearStats,
        completedLines.length,
        completedLines.filter((line) => line.orientation === "row").length,
        completedLines.filter((line) => line.orientation === "column").length,
        cleared.clearedCellCount
      );
      const nextMetrics = {
        ...typedPlayer.metrics,
        occupiedCells: cleared.board.occupiedCount,
        totalPlacedCells: typedPlayer.metrics.totalPlacedCells + shape.baseCells.length,
        placementsAccepted: typedPlayer.metrics.placementsAccepted + 1,
        lastClearedLines: completedLines.map((line) => ({
          orientation: line.orientation,
          index: line.index,
          color: line.color,
          cellCount: line.cellCount
        })),
        lastClearedCellCount: cleared.clearedCellCount
      };
      const score = calculateLineBreakerScore({
        lineClearStats: nextStats,
        metrics: nextMetrics,
        accumulatedTimeBonus: typedPlayer.accumulatedTimeBonus
      }).score;
      const secondary = completedLines.length * 1000 + cleared.clearedCellCount;

      if (score > bestScore || (score === bestScore && secondary > bestSecondary)) {
        best = candidate;
        bestScore = score;
        bestSecondary = secondary;
      }
    }

    return {
      anchorX: best.anchorX,
      anchorY: best.anchorY,
      rotation: best.rotation
    };
  }

  private finalizeCompletedMatch(
    state: DuplicateSessionStateInternal,
    document: GameSessionDocument,
    finishReason: "completed" | "sequence_exhausted" | "forfeit" | "cancelled",
    endedAt: Date
  ): DuplicateSessionStateInternal {
    state.phase = "finished";
    state.currentOffer = null;
    state.turn.deadlineAt = undefined;
    state.turn.awaitingPlayerIds = [];
    state.turn.lockedPlayerIds = [];
    state.turn.resolvedPlayerIds = state.players.map((player) => player.playerId);

    for (const playerState of state.players) {
      if (!playerState.result) {
        this.finishParticipant(
          state,
          playerState,
          playerState.finishReason ?? "sequence_exhausted",
          endedAt
        );
      }
    }

    state.result = this.buildMatchResult(state, document, finishReason, endedAt);

    return state;
  }

  private finishByForfeit(
    state: DuplicateSessionStateInternal,
    document: GameSessionDocument,
    playerId: string,
    reason: "forfeit" | "left"
  ): {
    state: DuplicateSessionStateInternal;
    endedAt: Date;
  } {
    const endedAt = this.nowProvider();
    const target = this.getParticipantState(state, playerId);
    this.finishParticipant(state, target, reason, endedAt);

    for (const participant of state.players) {
      if (!participant.finished && participant.playerId !== playerId) {
        this.finishParticipant(
          state,
          participant,
          participant.finishReason ?? "sequence_exhausted",
          endedAt
        );
      }
    }

    state = this.finalizeCompletedMatch(state, document, "forfeit", endedAt);

    return {
      state,
      endedAt
    };
  }

  private finishParticipant(
    state: DuplicateSessionStateInternal,
    playerState: DuplicateParticipantStateInternal,
    reason: DuplicateParticipantFinishReason,
    endedAt: Date
  ): void {
    playerState.finished = true;
    playerState.finishReason = reason;
    playerState.availablePlacementCount = 0;
    playerState.turnStatus = "finished";
    playerState.pendingPlacement = undefined;
    playerState.disconnectDeadlineAt = undefined;

    const result = cloneScore(
      "score" in playerState
        ? playerState.score
        : {
            score: 0,
            breakdown: {
              placementPoints: 0,
              objectivePoints: 0,
              lineClearPoints: 0,
              comboPoints: 0,
              timeBonus: 0,
              penalties: 0
            }
          }
    );
    result.metadata = {
      ...(result.metadata ?? {}),
      completedAt: endedAt.toISOString(),
      finishReason: reason
    };
    playerState.result = result;
  }

  private createImagePlayerState(
    puzzle: PuzzleTarget,
    playerId: string
  ): DuplicateImageRebuildParticipantStateInternal {
    const board = serializeBoard(createEmptyBoard(puzzle.gridWidth, puzzle.gridHeight));
    const metrics = calculateImageRebuildMetrics(board, puzzle, {
      invalidAttempts: 0,
      expiredTurns: 0
    });
    const score = calculateImageRebuildScore(metrics, {
      invalidAttempts: 0,
      expiredTurns: 0,
      accumulatedTimeBonus: 0
    });

    return {
      playerId,
      board,
      availablePlacementCount: 0,
      turnStatus: "skipped",
      finished: false,
      metrics,
      invalidAttempts: 0,
      accumulatedTimeBonus: 0,
      score
    };
  }

  private createLineBreakerPlayerState(
    playerId: string
  ): DuplicateLineBreakerParticipantStateInternal {
    const board = serializeBoard(createEmptyBoard(10, 10));

    return {
      playerId,
      board,
      availablePlacementCount: 0,
      turnStatus: "skipped",
      finished: false,
      accumulatedTimeBonus: 0,
      metrics: {
        occupiedCells: 0,
        totalPlacedCells: 0,
        placementsAccepted: 0,
        invalidAttempts: 0,
        expiredTurns: 0,
        lastClearedLines: [],
        lastClearedCellCount: 0
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
      }
    };
  }

  private incrementInvalidAttempt(
    state: DuplicateSessionStateInternal,
    playerId: string
  ): DuplicateSessionStateInternal {
    const playerState = this.getParticipantState(state, playerId);

    if (state.gameType === GameType.ImageRebuild) {
      const typed = playerState as DuplicateImageRebuildParticipantStateInternal;
      typed.invalidAttempts += 1;
      const puzzle = this.getPuzzleOrThrow(state.puzzleId);
      typed.metrics = calculateImageRebuildMetrics(typed.board, puzzle, {
        invalidAttempts: typed.invalidAttempts,
        expiredTurns: typed.metrics.expiredTurns
      });
      typed.score = calculateImageRebuildScore(typed.metrics, {
        invalidAttempts: typed.invalidAttempts,
        expiredTurns: typed.metrics.expiredTurns,
        accumulatedTimeBonus: typed.accumulatedTimeBonus
      });
    } else {
      const typed = playerState as DuplicateLineBreakerParticipantStateInternal;
      typed.metrics = {
        ...typed.metrics,
        invalidAttempts: typed.metrics.invalidAttempts + 1
      };
      typed.score = calculateLineBreakerScore({
        lineClearStats: typed.lineClearStats,
        metrics: typed.metrics,
        accumulatedTimeBonus: typed.accumulatedTimeBonus
      });
    }

    return state;
  }

  private incrementExpiredTurn(playerState: DuplicateParticipantStateInternal): void {
    if ("invalidAttempts" in playerState) {
      playerState.metrics.expiredTurns += 1;
      playerState.score = calculateImageRebuildScore(playerState.metrics, {
        invalidAttempts: playerState.invalidAttempts,
        expiredTurns: playerState.metrics.expiredTurns,
        accumulatedTimeBonus: playerState.accumulatedTimeBonus
      });
      return;
    }

    playerState.metrics = {
      ...playerState.metrics,
      expiredTurns: playerState.metrics.expiredTurns + 1
    };
    playerState.score = calculateLineBreakerScore({
      lineClearStats: playerState.lineClearStats,
      metrics: playerState.metrics,
      accumulatedTimeBonus: playerState.accumulatedTimeBonus
    });
  }

  private buildMatchResult(
    state: DuplicateSessionStateInternal,
    document: GameSessionDocument,
    finishReason: "completed" | "sequence_exhausted" | "forfeit" | "cancelled",
    endedAt: Date
  ): DuplicateMatchResult {
    const scores = state.players.map((playerState) => ({
      playerId: playerState.playerId,
      publicAlias: this.getSeatOrThrow(document, playerState.playerId).publicAlias,
      score: this.getPlayerScore(playerState),
      finishReason: playerState.finishReason
    }));
    const forfeitedPlayers = scores.filter(
      (player) => player.finishReason === "forfeit" || player.finishReason === "left"
    );

    let winnerPlayerId: string | undefined;
    let outcomes: Array<{
      playerId: string;
      publicAlias: string;
      outcome: DuplicateOutcome;
      score: number;
      finishReason?: DuplicateParticipantFinishReason;
    }>;

    if (forfeitedPlayers.length === 1) {
      winnerPlayerId = scores.find((player) => player.playerId !== forfeitedPlayers[0].playerId)?.playerId;
      outcomes = scores.map((player) => ({
        ...player,
        outcome: player.playerId === forfeitedPlayers[0].playerId ? "forfeit" : "win"
      }));
    } else if (forfeitedPlayers.length > 1) {
      outcomes = scores.map((player) => ({
        ...player,
        outcome: "draw"
      }));
    } else {
      const sorted = [...scores].sort((left, right) => right.score - left.score);
      const top = sorted[0];
      const second = sorted[1];

      if (!second || top.score === second.score) {
        outcomes = scores.map((player) => ({
          ...player,
          outcome: "draw"
        }));
      } else {
        winnerPlayerId = top.playerId;
        outcomes = scores.map((player) => ({
          ...player,
          outcome: player.playerId === top.playerId ? "win" : "loss"
        }));
      }
    }

    return {
      finishReason,
      winnerPlayerId,
      players: outcomes,
      endedAt: endedAt.toISOString()
    };
  }

  private createSequence(
    document: GameSessionDocument,
    state: DuplicateSessionStateInternal
  ): DeterministicBrickSequence {
    if (state.gameType === GameType.ImageRebuild) {
      const puzzle = this.getPuzzleOrThrow(state.puzzleId);
      return new DeterministicBrickSequence(
        `${document.seed}:${puzzle.id}:${IMAGE_DUPLICATE_RULESET_VERSION}`,
        INITIAL_BRICK_SHAPES,
        buildWeightedColorsForPuzzle(puzzle)
      );
    }

    return new DeterministicBrickSequence(
      `${document.seed}:${LINE_DUPLICATE_RULESET_VERSION}`,
      INITIAL_BRICK_SHAPES,
      LINE_BREAKER_COLOR_POOL.map((entry) => ({
        color: entry.color,
        weight: entry.weight
      }))
    );
  }

  private computeAvailablePlacementCount(board: BoardState, shapeId: string): number {
    const shape = this.getShapeOrThrow(shapeId);
    return listValidPlacementsForShape(hydrateBoardState(board), shape).length;
  }

  private syncDocumentScores(
    document: GameSessionDocument,
    state: DuplicateSessionStateInternal
  ): void {
    document.players = document.players.map((seat) => {
      const playerState = this.getParticipantState(state, seat.playerId);

      return {
        ...seat,
        score: this.getPlayerScore(playerState)
      };
    });
  }

  private buildSharedClock(
    state: DuplicateSessionStateInternal,
    referenceDate: Date,
    startedAt?: Date
  ): GameSessionDocument["sharedClock"] {
    return {
      startedAt,
      durationMs: state.turnTimeLimitMs,
      remainingMs: computeRemainingTurnMs(state.turn, referenceDate),
      tickRateMs: 1000
    };
  }

  private addPlayerState(
    state: DuplicateSessionStateInternal,
    playerId: string
  ): DuplicateSessionStateInternal {
    if (state.gameType === GameType.ImageRebuild) {
      const puzzle = this.getPuzzleOrThrow(state.puzzleId);
      return {
        ...state,
        players: [...state.players, this.createImagePlayerState(puzzle, playerId)]
      };
    }

    return {
      ...state,
      players: [...state.players, this.createLineBreakerPlayerState(playerId)]
    };
  }

  private removePlayerState(
    state: DuplicateSessionStateInternal,
    playerId: string
  ): DuplicateSessionStateInternal {
    return {
      ...state,
      players: state.players.filter((player) => player.playerId !== playerId)
    } as DuplicateSessionStateInternal;
  }

  private getSeatOrThrow(document: GameSessionDocument, playerId: string) {
    const seat = document.players.find((player) => player.playerId === playerId);

    if (!seat) {
      throw new DuplicateSessionError("PLAYER_NOT_IN_ROOM", "Player is not in this room.");
    }

    return seat;
  }

  private getParticipantState(
    state: DuplicateSessionStateInternal,
    playerId: string
  ): DuplicateParticipantStateInternal {
    const player = state.players.find((entry) => entry.playerId === playerId);

    if (!player) {
      throw new DuplicateSessionError("PLAYER_NOT_IN_ROOM", "Player state was not found.");
    }

    return player;
  }

  private getPlayerScore(player: DuplicateParticipantStateInternal): number {
    return player.result?.score ?? ("score" in player ? player.score.score : 0);
  }

  private getPuzzleOrThrow(puzzleId: string): PuzzleTarget {
    const puzzle = this.dependencies.puzzleCatalog.getTarget(puzzleId);

    if (!puzzle) {
      throw new DuplicateSessionError("PUZZLE_NOT_FOUND", `Unknown puzzle '${puzzleId}'.`);
    }

    return puzzle;
  }

  private getShapeOrThrow(shapeId: string) {
    const shape = this.shapesById.get(shapeId);

    if (!shape) {
      throw new DuplicateSessionError("SHAPE_NOT_FOUND", `Unknown brick shape '${shapeId}'.`);
    }

    return shape;
  }

  private computeLobbyPhase(document: GameSessionDocument): "waiting" | "ready" {
    return document.players.length === 2 && document.players.every((player) => player.ready)
      ? "ready"
      : "waiting";
  }

  private areAllPlayersFinished(state: DuplicateSessionStateInternal): boolean {
    return state.players.every((player) => player.finished);
  }

  private async requireDuplicateDocument(sessionId: string): Promise<GameSessionDocument> {
    const document = await this.dependencies.repositories.gameSessions.findDocumentBySessionId(
      sessionId
    );

    if (!document || document.mode !== GameMode.Duplicate2P) {
      throw new DuplicateSessionError(
        "SESSION_NOT_FOUND",
        `Unknown duplicate session '${sessionId}'.`
      );
    }

    return document;
  }

  private scheduleTurnTimer(
    sessionId: string,
    state: DuplicateSessionStateInternal,
    baseUrl: string
  ): void {
    if (this.stopped) {
      return;
    }

    this.clearTurnTimer(sessionId);

    if (state.phase !== "in_game" || !state.turn.deadlineAt) {
      return;
    }

    const delay = Math.max(0, Date.parse(state.turn.deadlineAt) - this.nowProvider().getTime());
    const timer = setTimeout(() => {
      if (this.stopped) {
        return;
      }

      void this
        .runLocked(sessionId, async () => {
          const document =
            await this.dependencies.repositories.gameSessions.findDocumentBySessionId(sessionId);

          if (!document || document.mode !== GameMode.Duplicate2P) {
            return;
          }

          const state = parseDuplicateState(document.sessionId, document.sessionState);
          await this.resolveTurn(document, state, this.nowProvider(), baseUrl);
        })
        .catch(() => undefined);
    }, delay);

    this.turnTimers.set(sessionId, timer);
  }

  private clearTurnTimer(sessionId: string): void {
    const timer = this.turnTimers.get(sessionId);

    if (!timer) {
      return;
    }

    clearTimeout(timer);
    this.turnTimers.delete(sessionId);
  }

  private scheduleReconnectTimer(
    sessionId: string,
    playerId: string,
    deadline: Date,
    baseUrl: string
  ): void {
    if (this.stopped) {
      return;
    }

    this.clearReconnectTimer(sessionId, playerId);
    const key = `${sessionId}:${playerId}`;
    const delay = Math.max(0, deadline.getTime() - this.nowProvider().getTime());
    const timer = setTimeout(() => {
      if (this.stopped) {
        return;
      }

      void this
        .runLocked(sessionId, async () => {
          const document =
            await this.dependencies.repositories.gameSessions.findDocumentBySessionId(sessionId);

          if (!document || document.mode !== GameMode.Duplicate2P) {
            return;
          }

          const state = parseDuplicateState(document.sessionId, document.sessionState);
          await this.resolveReconnectExpiry(document, state, playerId, deadline, baseUrl);
        })
        .catch(() => undefined);
    }, delay);

    this.reconnectTimers.set(key, timer);
  }

  private clearReconnectTimer(sessionId: string, playerId: string): void {
    const key = `${sessionId}:${playerId}`;
    const timer = this.reconnectTimers.get(key);

    if (!timer) {
      return;
    }

    clearTimeout(timer);
    this.reconnectTimers.delete(key);
  }

  private emitToSession(sessionId: string, message: unknown): void {
    if (this.stopped) {
      return;
    }

    this.notifier?.broadcastToSession(sessionId, message);
  }

  private async toPublicState(
    document: GameSessionDocument,
    state: DuplicateSessionStateInternal,
    baseUrl: string
  ): Promise<DuplicateRoomState> {
    const chatMessages = await this.dependencies.repositories.chatMessages.listBySessionId(
      document.sessionId,
      20
    );

    const common = {
      session: toGameSessionSummary(document),
      roomCode: document.roomCode ?? "",
      phase: state.phase,
      currentOffer: state.currentOffer,
      turn: state.turn,
      maxSequenceLength: state.maxSequenceLength,
      turnTimeLimitMs: state.turnTimeLimitMs,
      reconnectGraceMs: state.reconnectGraceMs,
      chatMessages,
      result: state.result,
      lastTurnResolution: state.lastTurnResolution
    };

    if (state.gameType === GameType.ImageRebuild) {
      return {
        ...common,
        gameType: GameType.ImageRebuild,
        puzzleId: state.puzzleId,
        puzzleName: state.puzzleName,
        puzzlePreviewUrl: this.dependencies.puzzleCatalog.buildPreviewUrl(
          state.puzzlePreviewImage,
          baseUrl
        ),
        targetBoard: state.targetBoard,
        paletteColors: state.paletteColors,
        players: document.players.map((seat) => {
          const playerState = this.getParticipantState(
            state,
            seat.playerId
          ) as DuplicateImageRebuildParticipantStateInternal;

          return {
            seat: seat.seat,
            playerId: seat.playerId,
            publicAlias: seat.publicAlias,
            connectionState: seat.connectionState,
            ready: seat.ready,
            score: this.getPlayerScore(playerState),
            board: playerState.board,
            availablePlacementCount: playerState.availablePlacementCount,
            turnStatus: playerState.turnStatus,
            finished: playerState.finished,
            finishReason: playerState.finishReason,
            disconnectDeadlineAt: playerState.disconnectDeadlineAt,
            result: playerState.result,
            metrics: playerState.metrics,
            invalidAttempts: playerState.invalidAttempts,
            accumulatedTimeBonus: playerState.accumulatedTimeBonus
          };
        })
      };
    }

    return {
      ...common,
      gameType: GameType.LineBreaker,
      colorPool: LINE_BREAKER_COLOR_POOL.map(({ weight: _weight, ...entry }) => entry),
      players: document.players.map((seat) => {
        const playerState = this.getParticipantState(
          state,
          seat.playerId
        ) as DuplicateLineBreakerParticipantStateInternal;

        return {
          seat: seat.seat,
          playerId: seat.playerId,
          publicAlias: seat.publicAlias,
          connectionState: seat.connectionState,
          ready: seat.ready,
          score: this.getPlayerScore(playerState),
          board: playerState.board,
          availablePlacementCount: playerState.availablePlacementCount,
          turnStatus: playerState.turnStatus,
          finished: playerState.finished,
          finishReason: playerState.finishReason,
          disconnectDeadlineAt: playerState.disconnectDeadlineAt,
          result: playerState.result,
          metrics: playerState.metrics,
          lineClearStats: playerState.lineClearStats,
          accumulatedTimeBonus: playerState.accumulatedTimeBonus
        };
      })
    };
  }

  private updateLineClearStats(
    current: DuplicateLineBreakerParticipantStateInternal["lineClearStats"],
    linesCleared: number,
    horizontalLines: number,
    verticalLines: number,
    clearedCellCount: number
  ): DuplicateLineBreakerParticipantStateInternal["lineClearStats"] {
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

  private async persistDuplicateHistory(
    document: GameSessionDocument,
    state: DuplicateSessionStateInternal
  ): Promise<void> {
    if (!state.result) {
      return;
    }

    const policyConfig = await this.getLoyaltyPolicyConfig();

    for (const participant of state.result.players) {
      const participantState = this.getParticipantState(state, participant.playerId);
      const reward = calculateLoyaltyReward({
        gameType: document.gameType,
        mode: GameMode.Duplicate2P,
        outcome: participant.outcome as HistoryOutcome,
        score: participant.score,
        finishReason: participant.finishReason ?? state.result.finishReason,
        policyConfig,
        imageMetrics:
          state.gameType === GameType.ImageRebuild
            ? {
                matchedCells: (
                  participantState as DuplicateImageRebuildParticipantStateInternal
                ).metrics.matchedCells,
                accuracyRatio: (
                  participantState as DuplicateImageRebuildParticipantStateInternal
                ).metrics.accuracyRatio,
                coverageRatio: (
                  participantState as DuplicateImageRebuildParticipantStateInternal
                ).metrics.coverageRatio
              }
            : undefined,
        lineMetrics:
          state.gameType === GameType.LineBreaker
            ? {
                totalLinesCleared: (
                  participantState as DuplicateLineBreakerParticipantStateInternal
                ).lineClearStats.totalLinesCleared,
                maxCombo: (
                  participantState as DuplicateLineBreakerParticipantStateInternal
                ).lineClearStats.maxCombo
              }
            : undefined
      });
      const inserted = await this.dependencies.repositories.history.create({
        historyId: `hist_${document.sessionId}_${participant.playerId}`,
        playerId: participant.playerId,
        sessionId: document.sessionId,
        gameType: document.gameType,
        mode: GameMode.Duplicate2P,
        outcome: participant.outcome,
        score: participant.score,
        rewardPoints: reward.points,
        opponentSummary: state.result.players
          .filter((player) => player.playerId !== participant.playerId)
          .map((player) => `${player.publicAlias}:${player.score}`)
          .join(", "),
        startedAt: document.startedAt ?? document.createdAt,
        endedAt: new Date(state.result.endedAt),
        metadata: {
          finishReason: state.result.finishReason,
          playerFinishReason: participant.finishReason,
          players: state.result.players,
          loyalty: {
            points: reward.points,
            formulaVersion: reward.formulaVersion,
            breakdown: reward.breakdown
          },
          gameSpecific:
            state.gameType === GameType.ImageRebuild
              ? {
                  puzzleId: state.puzzleId,
                  puzzleName: state.puzzleName,
                  metrics: (
                    participantState as DuplicateImageRebuildParticipantStateInternal
                  ).metrics,
                  formulaVersion: "image_rebuild_v1"
                }
              : {
                  metrics: (participantState as DuplicateLineBreakerParticipantStateInternal)
                    .metrics,
                  lineClearStats: (
                    participantState as DuplicateLineBreakerParticipantStateInternal
                  ).lineClearStats,
                  formulaVersion: "line_breaker_v1"
                }
        }
      });

      if (!inserted) {
        continue;
      }

      const loyaltySummary = await this.dependencies.repositories.players.applyLoyaltyDelta({
        playerId: participant.playerId,
        pointsDelta: reward.points
      });
      await this.dependencies.repositories.loyaltyLedger.append({
        playerId: participant.playerId,
        sessionId: document.sessionId,
        entryType: reward.entryType,
        pointsDelta: reward.points,
        balanceAfter: loyaltySummary.balance,
        expiresAt: reward.expiresAt,
        reason: reward.reason,
        gameType: document.gameType,
        mode: GameMode.Duplicate2P,
        outcome: participant.outcome as HistoryOutcome,
        score: participant.score,
        metadata: {
          finishReason: state.result.finishReason,
          playerFinishReason: participant.finishReason,
          formulaVersion: reward.formulaVersion,
          breakdown: reward.breakdown
        }
      });
      await this.dependencies.repositories.players.recordCompletedSession({
        playerId: participant.playerId,
        score: participant.score,
        endedAt: new Date(state.result.endedAt),
        outcome: participant.outcome as HistoryOutcome
      });
    }
  }

  private async generateUniqueRoomCode(): Promise<string> {
    for (let attempt = 0; attempt < 20; attempt += 1) {
      const roomCode = generateRoomCode();
      const existing = await this.dependencies.repositories.gameSessions.findDocumentByRoomCode(
        roomCode
      );

      if (!existing) {
        return roomCode;
      }
    }

    throw new DuplicateSessionError("ROOM_CODE_EXHAUSTED", "Unable to allocate a room code.");
  }

  private async runLocked<T>(sessionId: string, task: () => Promise<T>): Promise<T> {
    const previous = this.sessionLocks.get(sessionId) ?? Promise.resolve();
    let release: () => void = () => undefined;
    const current = new Promise<void>((resolve) => {
      release = resolve;
    });
    this.sessionLocks.set(sessionId, previous.then(() => current));
    await previous;

    try {
      return await task();
    } finally {
      release();
      this.sessionLocks.delete(sessionId);
    }
  }
}
