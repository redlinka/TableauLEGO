import { SharedColorDefinition } from "../colors/palette.js";
import { GameType } from "../enums.js";
import {
  BoardState,
  BrickOffer,
  GameSessionSummary,
  PlayerSeat,
  ScoreResult,
  TurnState
} from "./common.js";
import { ImageRebuildMetrics } from "./imageRebuild.js";
import {
  LineBreakerLineClearStats,
  LineBreakerMetrics
} from "./lineBreaker.js";

export type DuplicateRoomPhase =
  | "waiting"
  | "ready"
  | "in_game"
  | "finished"
  | "cancelled";

export type DuplicateParticipantTurnStatus =
  | "pending"
  | "locked"
  | "auto_placed"
  | "skipped"
  | "finished";

export type DuplicateParticipantFinishReason =
  | "no_more_moves"
  | "sequence_exhausted"
  | "forfeit"
  | "left"
  | "cancelled";

export type DuplicateOutcome = "win" | "loss" | "draw" | "forfeit";

export type DuplicateFinishReason =
  | "completed"
  | "sequence_exhausted"
  | "forfeit"
  | "cancelled";

export interface ChatMessageView {
  messageId: string;
  sessionId: string;
  playerId: string;
  publicAlias: string;
  content: string;
  createdAt: string;
}

export interface DuplicateTurnState extends TurnState {
  awaitingPlayerIds: string[];
  lockedPlayerIds: string[];
  resolvedPlayerIds: string[];
  lastResolvedAt?: string;
}

export interface DuplicateParticipantResult {
  playerId: string;
  publicAlias: string;
  outcome: DuplicateOutcome;
  score: number;
  finishReason?: DuplicateParticipantFinishReason;
}

export interface DuplicateMatchResult {
  finishReason: DuplicateFinishReason;
  winnerPlayerId?: string;
  players: DuplicateParticipantResult[];
  endedAt: string;
}

export interface DuplicateTurnResolutionPlayerSummary {
  playerId: string;
  publicAlias: string;
  mode: "submitted" | "auto_placed" | "skipped" | "already_finished" | "forfeit";
  accepted: boolean;
  scoreAfter: number;
  finished: boolean;
  finishReason?: DuplicateParticipantFinishReason;
  clearedLines?: number;
}

export interface DuplicateTurnResolution {
  turnIndex: number;
  offerId?: string;
  offer?: BrickOffer | null;
  resolvedAt: string;
  players: DuplicateTurnResolutionPlayerSummary[];
}

export interface DuplicateParticipantStateBase {
  seat: PlayerSeat["seat"];
  playerId: string;
  publicAlias: string;
  connectionState: PlayerSeat["connectionState"];
  ready: boolean;
  score: number;
  board: BoardState;
  availablePlacementCount: number;
  turnStatus: DuplicateParticipantTurnStatus;
  finished: boolean;
  finishReason?: DuplicateParticipantFinishReason;
  disconnectDeadlineAt?: string;
  result?: ScoreResult;
}

export interface DuplicateImageRebuildParticipantState
  extends DuplicateParticipantStateBase {
  metrics: ImageRebuildMetrics;
  invalidAttempts: number;
  accumulatedTimeBonus: number;
}

export interface DuplicateLineBreakerParticipantState
  extends DuplicateParticipantStateBase {
  metrics: LineBreakerMetrics;
  lineClearStats: LineBreakerLineClearStats;
  accumulatedTimeBonus: number;
}

export interface DuplicateRoomStateBase {
  session: GameSessionSummary;
  roomCode: string;
  phase: DuplicateRoomPhase;
  currentOffer: BrickOffer | null;
  turn: DuplicateTurnState;
  maxSequenceLength: number;
  turnTimeLimitMs: number;
  reconnectGraceMs: number;
  chatMessages: ChatMessageView[];
  result?: DuplicateMatchResult;
  lastTurnResolution?: DuplicateTurnResolution;
}

export interface DuplicateImageRebuildRoomState extends DuplicateRoomStateBase {
  gameType: GameType.ImageRebuild;
  puzzleId: string;
  puzzleName: string;
  puzzlePreviewUrl: string;
  targetBoard: BoardState;
  paletteColors?: Record<string, string>;
  players: DuplicateImageRebuildParticipantState[];
}

export interface DuplicateLineBreakerRoomState extends DuplicateRoomStateBase {
  gameType: GameType.LineBreaker;
  colorPool: SharedColorDefinition[];
  players: DuplicateLineBreakerParticipantState[];
}

export type DuplicateRoomState =
  | DuplicateImageRebuildRoomState
  | DuplicateLineBreakerRoomState;
