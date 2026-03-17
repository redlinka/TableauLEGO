import {
  BoardState,
  BrickOffer,
  GameSessionSummary,
  ScoreResult,
  SoloFinishReason,
  TurnState
} from "./common.js";

export type LineOrientation = "row" | "column";

export interface ClearedLineSummary {
  orientation: LineOrientation;
  index: number;
  color: string;
  cellCount: number;
}

export interface LineBreakerLineClearStats {
  totalLinesCleared: number;
  horizontalLinesCleared: number;
  verticalLinesCleared: number;
  simultaneousClearTurns: number;
  maxLinesSingleTurn: number;
  totalClearedCells: number;
  currentCombo: number;
  maxCombo: number;
  comboBonusTotal: number;
  multiLineBonusTotal: number;
}

export interface LineBreakerMetrics {
  occupiedCells: number;
  totalPlacedCells: number;
  placementsAccepted: number;
  invalidAttempts: number;
  expiredTurns: number;
  lastClearedLines: ClearedLineSummary[];
  lastClearedCellCount: number;
}

export interface LineBreakerSessionState {
  session: GameSessionSummary;
  turn: TurnState;
  board: BoardState;
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
}

export interface LineBreakerPlacementAttemptResponse {
  accepted: boolean;
  rejectionReason?: string;
  state: LineBreakerSessionState;
}
