import {
  BoardState,
  BrickOffer,
  GameSessionSummary,
  ScoreResult,
  SoloFinishReason,
  TurnState
} from "./common.js";

export interface ImageRebuildMetrics {
  totalCells: number;
  occupiedCells: number;
  matchedCells: number;
  mismatchedCells: number;
  emptyCells: number;
  coverageRatio: number;
  accuracyRatio: number;
  invalidAttempts: number;
  expiredTurns: number;
}

export interface ImageRebuildSessionState {
  session: GameSessionSummary;
  puzzleId: string;
  puzzleName: string;
  puzzlePreviewUrl: string;
  turn: TurnState;
  board: BoardState;
  currentOffer: BrickOffer | null;
  availablePlacementCount: number;
  score: ScoreResult;
  metrics: ImageRebuildMetrics;
  maxSequenceLength: number;
  turnTimeLimitMs: number;
  finished: boolean;
  finishReason?: SoloFinishReason;
  result?: ScoreResult;
}

export interface PlacementAttemptResponse {
  accepted: boolean;
  rejectionReason?: string;
  state: ImageRebuildSessionState;
}
