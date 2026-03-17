import { AuthSource } from "@games-platform/game-contracts";
import mongoose, { Model } from "mongoose";

export interface PlayerDocument {
  playerId: string;
  authSource: AuthSource;
  publicAlias: string;
  externalRef?: {
    issuer: string;
    subject: string;
  };
  technicalLoyaltyId?: string;
  lastSeenAt: Date;
  statsSummary: {
    totalSessions: number;
    totalScore: number;
    wins: number;
    losses: number;
    lastPlayedAt?: Date;
  };
  loyaltySummary: {
    balance: number;
    lifetimeEarned: number;
  };
  createdAt: Date;
  updatedAt: Date;
}

const playerSchema = new mongoose.Schema<PlayerDocument>(
  {
    playerId: {
      type: String,
      required: true,
      unique: true,
      index: true
    },
    authSource: {
      type: String,
      enum: Object.values(AuthSource),
      required: true
    },
    publicAlias: {
      type: String,
      required: true,
      trim: true
    },
    externalRef: {
      issuer: { type: String },
      subject: { type: String }
    },
    technicalLoyaltyId: {
      type: String
    },
    lastSeenAt: {
      type: Date,
      default: () => new Date(),
      index: true
    },
    statsSummary: {
      totalSessions: { type: Number, default: 0 },
      totalScore: { type: Number, default: 0 },
      wins: { type: Number, default: 0 },
      losses: { type: Number, default: 0 },
      lastPlayedAt: { type: Date }
    },
    loyaltySummary: {
      balance: { type: Number, default: 0 },
      lifetimeEarned: { type: Number, default: 0 }
    }
  },
  {
    collection: "players",
    timestamps: true
  }
);

export const PlayerModel: Model<PlayerDocument> =
  mongoose.models.Player ?? mongoose.model<PlayerDocument>("Player", playerSchema);
