import {
  BoardState,
  BrickOffer,
  ImageRebuildMetrics,
  ScoreResult,
  SoloFinishReason,
  TurnState
} from "@games-platform/game-contracts";

export interface ImageRebuildPersistedState {
  puzzleId: string;
  puzzleName: string;
  puzzlePreviewImage: string;
  board: BoardState;
  turn: TurnState;
  currentOffer: BrickOffer | null;
  availablePlacementCount: number;
  score: ScoreResult;
  metrics: ImageRebuildMetrics;
  maxSequenceLength: number;
  turnTimeLimitMs: number;
  finished: boolean;
  finishReason?: SoloFinishReason;
  result?: ScoreResult;
  invalidAttempts: number;
  accumulatedTimeBonus: number;
  eventSeq: number;
}
