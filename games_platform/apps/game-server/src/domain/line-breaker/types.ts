import {
  BoardState,
  BrickOffer,
  LineBreakerLineClearStats,
  LineBreakerMetrics,
  ScoreResult,
  SoloFinishReason,
  TurnState
} from "@games-platform/game-contracts";

export interface LineBreakerPersistedState {
  board: BoardState;
  turn: TurnState;
  currentOffer: BrickOffer | null;
  availablePlacementCount: number;
  score: ScoreResult;
  lineClearStats: LineBreakerLineClearStats;
  metrics: LineBreakerMetrics;
  maxSequenceLength: number;
  turnTimeLimitMs: number;
  finished: boolean;
  finishReason?: SoloFinishReason;
  result?: ScoreResult;
  accumulatedTimeBonus: number;
  eventSeq: number;
}
