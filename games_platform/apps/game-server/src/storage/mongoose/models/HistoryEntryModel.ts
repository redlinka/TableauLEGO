import { GameMode, GameType } from "@games-platform/game-contracts";
import mongoose, { Model } from "mongoose";

export interface HistoryEntryDocument {
  historyId: string;
  playerId: string;
  sessionId: string;
  gameType: GameType;
  mode: GameMode;
  outcome: "win" | "loss" | "draw" | "solo" | "abandoned" | "forfeit";
  score: number;
  rewardPoints?: number;
  opponentSummary?: string;
  startedAt: Date;
  endedAt: Date;
  metadata?: Record<string, unknown>;
  createdAt: Date;
  updatedAt: Date;
}

const historyEntrySchema = new mongoose.Schema<HistoryEntryDocument>(
  {
    historyId: {
      type: String,
      required: true,
      unique: true
    },
    playerId: {
      type: String,
      required: true
    },
    sessionId: {
      type: String,
      required: true
    },
    gameType: {
      type: String,
      enum: Object.values(GameType),
      required: true
    },
    mode: {
      type: String,
      enum: Object.values(GameMode),
      required: true
    },
    outcome: {
      type: String,
      enum: ["win", "loss", "draw", "solo", "abandoned", "forfeit"],
      required: true
    },
    score: {
      type: Number,
      required: true
    },
    rewardPoints: {
      type: Number
    },
    opponentSummary: {
      type: String
    },
    startedAt: {
      type: Date,
      required: true
    },
    endedAt: {
      type: Date,
      required: true
    },
    metadata: {
      type: mongoose.Schema.Types.Mixed
    }
  },
  {
    collection: "history_entries",
    timestamps: true
  }
);

historyEntrySchema.index({ playerId: 1, endedAt: -1 });
historyEntrySchema.index({ sessionId: 1 });

export const HistoryEntryModel: Model<HistoryEntryDocument> =
  mongoose.models.HistoryEntry ??
  mongoose.model<HistoryEntryDocument>("HistoryEntry", historyEntrySchema);
