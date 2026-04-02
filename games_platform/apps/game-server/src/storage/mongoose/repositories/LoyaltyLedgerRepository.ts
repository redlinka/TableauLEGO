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
    remainingPoints: document.remainingPoints,
    expiresAt: document.expiresAt?.toISOString(),
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

  async append(input: Omit<LoyaltyLedgerEntry, "entryId" | "createdAt"> & { expiresAt?: string }): Promise<void> {
    await LoyaltyLedgerModel.create({
      entryId: `led_${randomUUID()}`,
      playerId: input.playerId,
      sessionId: input.sessionId,
      entryType: input.entryType,
      pointsDelta: input.pointsDelta,
      balanceAfter: input.balanceAfter,
      remainingPoints: input.pointsDelta > 0 ? input.pointsDelta : undefined,
      expiresAt: input.expiresAt ? new Date(input.expiresAt) : undefined,
      reason: input.reason,
      gameType: input.gameType,
      mode: input.mode,
      outcome: input.outcome,
      score: input.score,
      metadata: input.metadata
    });
  }

  /**
   * Consume loyalty points using FIFO by expiration date.
   * Points expiring soonest are consumed first, as required by the spec.
   * Returns the total points actually consumed.
   */
  async consumePointsFifo(playerId: string, pointsToConsume: number): Promise<number> {
    let remaining = pointsToConsume;

    const entries = await LoyaltyLedgerModel.find({
      playerId,
      pointsDelta: { $gt: 0 },
      remainingPoints: { $gt: 0 },
      $or: [
        { expiresAt: { $gt: new Date() } },
        { expiresAt: { $exists: false } },
        { expiresAt: null }
      ]
    })
      .sort({ expiresAt: 1, createdAt: 1 })
      .lean<LoyaltyLedgerDocument[]>();

    for (const entry of entries) {
      if (remaining <= 0) {
        break;
      }

      const available = entry.remainingPoints ?? 0;
      const deducted = Math.min(available, remaining);

      await LoyaltyLedgerModel.updateOne(
        { entryId: entry.entryId },
        { $inc: { remainingPoints: -deducted } }
      );

      remaining -= deducted;
    }

    return pointsToConsume - remaining;
  }

  /**
   * Get the player's available balance considering only non-expired points.
   */
  async getAvailableBalance(playerId: string): Promise<number> {
    const result = await LoyaltyLedgerModel.aggregate<{ total: number }>([
      {
        $match: {
          playerId,
          pointsDelta: { $gt: 0 },
          remainingPoints: { $gt: 0 },
          $or: [
            { expiresAt: { $gt: new Date() } },
            { expiresAt: { $exists: false } },
            { expiresAt: null }
          ]
        }
      },
      {
        $group: {
          _id: null,
          total: { $sum: "$remainingPoints" }
        }
      }
    ]);

    return result[0]?.total ?? 0;
  }
}
