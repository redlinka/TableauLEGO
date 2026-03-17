import {
  BoardState,
  BrickOffer,
  DuplicateFinishReason,
  DuplicateParticipantFinishReason,
  DuplicateParticipantTurnStatus,
  DuplicateRoomPhase,
  DuplicateTurnResolution,
  DuplicateTurnState,
  ImageRebuildMetrics,
  LineBreakerLineClearStats,
  LineBreakerMetrics,
  ScoreResult
} from "@games-platform/game-contracts";

export interface DuplicateParticipantStateBaseInternal {
  playerId: string;
  board: BoardState;
  availablePlacementCount: number;
  turnStatus: DuplicateParticipantTurnStatus;
  finished: boolean;
  finishReason?: DuplicateParticipantFinishReason;
  disconnectDeadlineAt?: string;
  pendingPlacement?: {
    offerId: string;
    anchorX: number;
    anchorY: number;
    rotation: number;
    submittedAt: string;
  };
  result?: ScoreResult;
}

export interface DuplicateImageRebuildParticipantStateInternal
  extends DuplicateParticipantStateBaseInternal {
  metrics: ImageRebuildMetrics;
  invalidAttempts: number;
  accumulatedTimeBonus: number;
  score: ScoreResult;
}

export interface DuplicateLineBreakerParticipantStateInternal
  extends DuplicateParticipantStateBaseInternal {
  metrics: LineBreakerMetrics;
  lineClearStats: LineBreakerLineClearStats;
  accumulatedTimeBonus: number;
  score: ScoreResult;
}

export type DuplicateParticipantStateInternal =
  | DuplicateImageRebuildParticipantStateInternal
  | DuplicateLineBreakerParticipantStateInternal;

export interface DuplicateSessionStateBaseInternal {
  phase: DuplicateRoomPhase;
  currentOffer: BrickOffer | null;
  turn: DuplicateTurnState;
  maxSequenceLength: number;
  turnTimeLimitMs: number;
  reconnectGraceMs: number;
  lastTurnResolution?: DuplicateTurnResolution;
  result?: {
    finishReason: DuplicateFinishReason;
    winnerPlayerId?: string;
    players: Array<{
      playerId: string;
      publicAlias: string;
      outcome: "win" | "loss" | "draw" | "forfeit";
      score: number;
      finishReason?: DuplicateParticipantFinishReason;
    }>;
    endedAt: string;
  };
  eventSeq: number;
}

export interface DuplicateImageRebuildSessionStateInternal
  extends DuplicateSessionStateBaseInternal {
  gameType: "image_rebuild";
  puzzleId: string;
  puzzleName: string;
  puzzlePreviewImage: string;
  targetBoard: BoardState;
  paletteColors?: Record<string, string>;
  players: DuplicateImageRebuildParticipantStateInternal[];
}

export interface DuplicateLineBreakerSessionStateInternal
  extends DuplicateSessionStateBaseInternal {
  gameType: "line_breaker";
  players: DuplicateLineBreakerParticipantStateInternal[];
}

export type DuplicateSessionStateInternal =
  | DuplicateImageRebuildSessionStateInternal
  | DuplicateLineBreakerSessionStateInternal;
