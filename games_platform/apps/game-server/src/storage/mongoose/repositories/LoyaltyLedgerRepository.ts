import { randomUUID } from "node:crypto";
import { LoyaltyLedgerEntry } from "@games-platform/game-contracts";
import { LoyaltyLedgerDocument, LoyaltyLedgerModel } from "../models/LoyaltyLedgerModel.js";

function toLedgerEntry(document: LoyaltyLedgerDocument): LoyaltyLedgerEntry {
  return {
    entryId: document.entryId,
    playerId: document.playerId,
    sessionId: document.sessionId,
    entryType: document.entryType,
    pointsDelta: document.pointsDelta,
    balanceAfter: document.balanceAfter,
    reason: document.reason,
    gameType: document.gameType,
    mode: document.mode,
    outcome: document.outcome,
    score: document.score,
    metadata: document.metadata,
    createdAt: document.createdAt.toISOString()
  };
}

export class LoyaltyLedgerRepository {
  async countByPlayerId(playerId: string): Promise<number> {
    return LoyaltyLedgerModel.countDocuments({ playerId });
  }

  async listByPlayerId(playerId: string, limit = 20): Promise<LoyaltyLedgerEntry[]> {
    const entries = await LoyaltyLedgerModel.find({ playerId })
      .sort({ createdAt: -1 })
      .limit(limit)
      .lean<LoyaltyLedgerDocument[]>();

    return entries.map(toLedgerEntry);
  }

  async listByPlayerSession(playerId: string, sessionId: string): Promise<LoyaltyLedgerEntry[]> {
    const entries = await LoyaltyLedgerModel.find({ playerId, sessionId })
      .sort({ createdAt: -1 })
      .lean<LoyaltyLedgerDocument[]>();

    return entries.map(toLedgerEntry);
  }

  async append(input: Omit<LoyaltyLedgerEntry, "entryId" | "createdAt">): Promise<void> {
    await LoyaltyLedgerModel.create({
      entryId: `led_${randomUUID()}`,
      playerId: input.playerId,
      sessionId: input.sessionId,
      entryType: input.entryType,
      pointsDelta: input.pointsDelta,
      balanceAfter: input.balanceAfter,
      reason: input.reason,
      gameType: input.gameType,
      mode: input.mode,
      outcome: input.outcome,
      score: input.score,
      metadata: input.metadata
    });
  }
}
