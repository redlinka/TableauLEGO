import { GameMode, GameType, HistoryOutcome, LoyaltyEntryType } from "@games-platform/game-contracts";
import mongoose, { Model } from "mongoose";

export interface LoyaltyLedgerDocument {
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
  metadata?: Record<string, unknown>;
  createdAt: Date;
  updatedAt: Date;
}

const loyaltyLedgerSchema = new mongoose.Schema<LoyaltyLedgerDocument>(
  {
    entryId: {
      type: String,
      required: true,
      unique: true
    },
    playerId: {
      type: String,
      required: true
    },
    sessionId: {
      type: String
    },
    entryType: {
      type: String,
      enum: Object.values(LoyaltyEntryType),
      required: true
    },
    pointsDelta: {
      type: Number,
      required: true
    },
    balanceAfter: {
      type: Number
    },
    reason: {
      type: String,
      required: true
    },
    gameType: {
      type: String,
      enum: Object.values(GameType)
    },
    mode: {
      type: String,
      enum: Object.values(GameMode)
    },
    outcome: {
      type: String,
      enum: ["win", "loss", "draw", "solo", "abandoned", "forfeit"]
    },
    score: {
      type: Number
    },
    metadata: {
      type: mongoose.Schema.Types.Mixed
    }
  },
  {
    collection: "loyalty_ledger",
    timestamps: true
  }
);

loyaltyLedgerSchema.index({ playerId: 1, createdAt: -1 });
loyaltyLedgerSchema.index({ sessionId: 1 });

export const LoyaltyLedgerModel: Model<LoyaltyLedgerDocument> =
  mongoose.models.LoyaltyLedger ??
  mongoose.model<LoyaltyLedgerDocument>("LoyaltyLedger", loyaltyLedgerSchema);
