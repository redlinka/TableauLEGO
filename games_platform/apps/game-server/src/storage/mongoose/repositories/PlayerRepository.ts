import {
  AuthSource,
  HistoryOutcome,
  LoyaltySummary,
  PlayerStatsSummary,
  TechnicalPlayerIdentity
} from "@games-platform/game-contracts";
import { randomUUID } from "node:crypto";
import { PlayerDocument, PlayerModel } from "../models/PlayerModel.js";

function sanitizeAlias(input?: string): string {
  const candidate = (input ?? "").trim().replace(/\s+/g, " ");

  if (!candidate) {
    return `Guest-${randomUUID().slice(0, 6).toUpperCase()}`;
  }

  return candidate.slice(0, 24);
}

function toIdentity(document: PlayerDocument): TechnicalPlayerIdentity {
  return {
    playerId: document.playerId,
    authSource: document.authSource,
    publicAlias: document.publicAlias
  };
}

export class PlayerRepository {
  async createGuestPlayer(preferredAlias?: string): Promise<TechnicalPlayerIdentity> {
    const created = await PlayerModel.create({
      playerId: `ply_${randomUUID()}`,
      authSource: AuthSource.Guest,
      publicAlias: sanitizeAlias(preferredAlias),
      lastSeenAt: new Date()
    });

    return toIdentity(created.toObject());
  }

  async findByPlayerId(playerId: string): Promise<TechnicalPlayerIdentity | null> {
    const found = await PlayerModel.findOne({ playerId }).lean<PlayerDocument | null>();

    return found ? toIdentity(found) : null;
  }

  async ensureTechnicalPlayer(
    identity: TechnicalPlayerIdentity,
    options?: {
      externalRef?: {
        issuer: string;
        subject: string;
      };
      technicalLoyaltyId?: string;
    }
  ): Promise<TechnicalPlayerIdentity> {
    const updated = await PlayerModel.findOneAndUpdate(
      { playerId: identity.playerId },
      {
        $set: {
          authSource: identity.authSource,
          publicAlias: identity.publicAlias,
          lastSeenAt: new Date(),
          ...(options?.externalRef
            ? {
                externalRef: options.externalRef
              }
            : {}),
          ...(options?.technicalLoyaltyId
            ? {
                technicalLoyaltyId: options.technicalLoyaltyId
              }
            : {})
        }
      },
      {
        upsert: true,
        new: true,
        setDefaultsOnInsert: true
      }
    ).lean<PlayerDocument>();

    return toIdentity(updated);
  }

  async touch(playerId: string): Promise<void> {
    await PlayerModel.updateOne(
      { playerId },
      {
        $set: {
          lastSeenAt: new Date()
        }
      }
    );
  }

  async recordCompletedSession(input: {
    playerId: string;
    score: number;
    endedAt: Date;
    outcome?: HistoryOutcome;
  }): Promise<void> {
    const wins = input.outcome === "win" ? 1 : 0;
    const losses =
      input.outcome === "loss" || input.outcome === "forfeit" ? 1 : 0;

    await PlayerModel.updateOne(
      { playerId: input.playerId },
      {
        $inc: {
          "statsSummary.totalSessions": 1,
          "statsSummary.totalScore": input.score,
          "statsSummary.wins": wins,
          "statsSummary.losses": losses
        },
        $set: {
          lastSeenAt: input.endedAt,
          "statsSummary.lastPlayedAt": input.endedAt
        }
      }
    );
  }

  async applyLoyaltyDelta(input: {
    playerId: string;
    pointsDelta: number;
  }): Promise<LoyaltySummary> {
    const updated = await PlayerModel.findOneAndUpdate(
      { playerId: input.playerId },
      {
        $inc: {
          "loyaltySummary.balance": input.pointsDelta,
          "loyaltySummary.lifetimeEarned": Math.max(0, input.pointsDelta)
        },
        $set: {
          lastSeenAt: new Date()
        }
      },
      {
        new: true
      }
    ).lean<PlayerDocument | null>();

    if (!updated) {
      throw new Error(`Unknown player '${input.playerId}' for loyalty delta.`);
    }

    return {
      playerId: updated.playerId,
      balance: updated.loyaltySummary.balance,
      lifetimeEarned: updated.loyaltySummary.lifetimeEarned,
      totalEntries: 0,
      lastUpdatedAt: updated.updatedAt.toISOString(),
      technicalLoyaltyId: updated.technicalLoyaltyId
    };
  }

  async getProfile(playerId: string): Promise<{
    identity: TechnicalPlayerIdentity;
    statsSummary: PlayerStatsSummary;
    loyaltySummary: LoyaltySummary;
  } | null> {
    const found = await PlayerModel.findOne({ playerId }).lean<PlayerDocument | null>();

    if (!found) {
      return null;
    }

    return {
      identity: toIdentity(found),
      statsSummary: {
        totalSessions: found.statsSummary.totalSessions,
        totalScore: found.statsSummary.totalScore,
        wins: found.statsSummary.wins,
        losses: found.statsSummary.losses,
        lastPlayedAt: found.statsSummary.lastPlayedAt?.toISOString()
      },
      loyaltySummary: {
        playerId: found.playerId,
        balance: found.loyaltySummary.balance,
        lifetimeEarned: found.loyaltySummary.lifetimeEarned,
        totalEntries: 0,
        lastUpdatedAt: found.updatedAt.toISOString(),
        technicalLoyaltyId: found.technicalLoyaltyId
      }
    };
  }
}
