import { GameMode, GameType, LoyaltyEntryType } from "../enums.js";

export interface ImageRebuildHistoryMetadata {
  puzzleId: string;
  puzzleName: string;
  finishReason?: string;
  metrics: Record<string, unknown>;
  formulaVersion: string;
}

export interface LineBreakerHistoryMetadata {
  finishReason?: string;
  lineClearStats: Record<string, unknown>;
  metrics: Record<string, unknown>;
  formulaVersion: string;
}

export type HistoryOutcome =
  | "win"
  | "loss"
  | "draw"
  | "solo"
  | "abandoned"
  | "forfeit";

export interface HistoryEntry {
  historyId: string;
  playerId: string;
  sessionId: string;
  gameType: GameType;
  mode: GameMode;
  outcome: HistoryOutcome;
  score: number;
  rewardPoints?: number;
  opponentSummary?: string;
  startedAt: string;
  endedAt: string;
  durationMs: number;
  finishReason?: string;
  metadata?: Record<string, unknown>;
}

export interface LoyaltyLedgerEntry {
  entryId: string;
  playerId: string;
  sessionId?: string;
  entryType: LoyaltyEntryType;
  pointsDelta: number;
  balanceAfter?: number;
  reason: string;
  gameType?: GameType;
  mode?: GameMode;
  outcome?: HistoryOutcome;
  score?: number;
  createdAt: string;
  metadata?: Record<string, unknown>;
}

export interface LoyaltySummary {
  playerId: string;
  balance: number;
  lifetimeEarned: number;
  totalEntries: number;
  lastUpdatedAt?: string;
  technicalLoyaltyId?: string;
}
