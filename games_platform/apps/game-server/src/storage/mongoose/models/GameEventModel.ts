import { EventType } from "@games-platform/game-contracts";
import mongoose, { Model } from "mongoose";

export interface GameEventDocument {
  eventId: string;
  sessionId: string;
  seq: number;
  type: EventType;
  actorPlayerId?: string;
  payload?: Record<string, unknown>;
  createdAt: Date;
}

const gameEventSchema = new mongoose.Schema<GameEventDocument>(
  {
    eventId: {
      type: String,
      required: true,
      unique: true
    },
    sessionId: {
      type: String,
      required: true
    },
    seq: {
      type: Number,
      required: true
    },
    type: {
      type: String,
      enum: Object.values(EventType),
      required: true
    },
    actorPlayerId: {
      type: String
    },
    payload: {
      type: mongoose.Schema.Types.Mixed
    },
    createdAt: {
      type: Date,
      default: () => new Date()
    }
  },
  {
    collection: "game_events",
    versionKey: false
  }
);

gameEventSchema.index({ sessionId: 1, seq: 1 }, { unique: true });
gameEventSchema.index({ actorPlayerId: 1, createdAt: -1 });

export const GameEventModel: Model<GameEventDocument> =
  mongoose.models.GameEvent ??
  mongoose.model<GameEventDocument>("GameEvent", gameEventSchema);
