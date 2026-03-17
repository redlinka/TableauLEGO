import {
  AuthSource,
  ConnectionState,
  GameMode,
  GameType,
  PlayerSeatType,
  SessionStatus
} from "../enums.js";

export interface CellOffset {
  x: number;
  y: number;
}

export interface PlacementSnapshot {
  shapeId: string;
  color: string;
  anchorX: number;
  anchorY: number;
  rotation: number;
  placedCells: CellOffset[];
  offerId?: string;
}

export interface BoardState {
  width: number;
  height: number;
  cells: (string | null)[][];
  occupiedCount: number;
  lastPlacement?: PlacementSnapshot;
}

export type SoloFinishReason =
  | "no_more_moves"
  | "sequence_exhausted"
  | "abandoned";

export interface TurnState {
  index: number;
  startedAt?: string;
  deadlineAt?: string;
  limitMs: number;
  skippedOffers: number;
  expiredTurns: number;
}

export interface TechnicalPlayerIdentity {
  playerId: string;
  authSource: AuthSource;
  publicAlias: string;
}

export interface PlayerStatsSummary {
  totalSessions: number;
  totalScore: number;
  wins: number;
  losses: number;
  lastPlayedAt?: string;
}

export interface PlayerSeat {
  seat: PlayerSeatType;
  playerId: string;
  publicAlias: string;
  connectionState: ConnectionState;
  ready: boolean;
  score: number;
  resumeTokenHash?: string | null;
}

export interface SharedClock {
  startedAt?: string;
  durationMs?: number;
  remainingMs?: number;
  tickRateMs?: number;
}

export interface BrickOffer {
  offerId: string;
  shapeId: string;
  color: string;
  issuedAtTurn: number;
  sequenceIndex: number;
}

export interface ScoreBreakdown {
  placementPoints: number;
  objectivePoints: number;
  lineClearPoints: number;
  comboPoints: number;
  timeBonus: number;
  penalties: number;
}

export interface ScoreResult {
  score: number;
  breakdown: ScoreBreakdown;
  rank?: number;
  accuracy?: number;
  linesCleared?: number;
  timeBonus?: number;
  metadata?: Record<string, unknown>;
}

export interface GameSessionSummary {
  sessionId: string;
  mode: GameMode;
  gameType: GameType;
  status: SessionStatus;
  roomCode?: string;
  hostPlayerId?: string;
  createdAt: string;
  startedAt?: string;
  endedAt?: string;
}
