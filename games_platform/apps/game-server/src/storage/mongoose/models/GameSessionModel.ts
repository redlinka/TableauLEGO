import {
  ConnectionState,
  GameMode,
  GameType,
  PlayerSeatType,
  SessionStatus
} from "@games-platform/game-contracts";
import mongoose, { Model } from "mongoose";

export interface GameSessionDocument {
  sessionId: string;
  mode: GameMode;
  gameType: GameType;
  status: SessionStatus;
  rulesetVersion: string;
  seed: string;
  roomCode?: string;
  hostPlayerId?: string;
  players: Array<{
    seat: PlayerSeatType;
    playerId: string;
    publicAlias: string;
    connectionState: ConnectionState;
    ready: boolean;
    score: number;
    resumeTokenHash?: string;
  }>;
  sharedClock?: {
    startedAt?: Date;
    durationMs?: number;
    remainingMs?: number;
    tickRateMs?: number;
  };
  currentTurn: number;
  sessionState?: unknown;
  result?: unknown;
  createdAt: Date;
  updatedAt: Date;
  startedAt?: Date;
  endedAt?: Date;
}

const playerSeatSchema = new mongoose.Schema<GameSessionDocument["players"][number]>(
  {
    seat: {
      type: String,
      enum: Object.values(PlayerSeatType),
      required: true
    },
    playerId: {
      type: String,
      required: true
    },
    publicAlias: {
      type: String,
      required: true
    },
    connectionState: {
      type: String,
      enum: Object.values(ConnectionState),
      required: true
    },
    ready: {
      type: Boolean,
      default: false
    },
    score: {
      type: Number,
      default: 0
    },
    resumeTokenHash: {
      type: String
    }
  },
  { _id: false }
);

const gameSessionSchema = new mongoose.Schema<GameSessionDocument>(
  {
    sessionId: {
      type: String,
      required: true,
      unique: true
    },
    mode: {
      type: String,
      enum: Object.values(GameMode),
      required: true
    },
    gameType: {
      type: String,
      enum: Object.values(GameType),
      required: true
    },
    status: {
      type: String,
      enum: Object.values(SessionStatus),
      required: true
    },
    rulesetVersion: {
      type: String,
      required: true
    },
    seed: {
      type: String,
      required: true
    },
    roomCode: {
      type: String
    },
    hostPlayerId: {
      type: String
    },
    players: {
      type: [playerSeatSchema],
      default: []
    },
    sharedClock: {
      startedAt: { type: Date },
      durationMs: { type: Number },
      remainingMs: { type: Number },
      tickRateMs: { type: Number }
    },
    currentTurn: {
      type: Number,
      default: 0
    },
    sessionState: {
      type: mongoose.Schema.Types.Mixed
    },
    result: {
      type: mongoose.Schema.Types.Mixed
    },
    startedAt: { type: Date },
    endedAt: { type: Date }
  },
  {
    collection: "game_sessions",
    timestamps: true
  }
);

gameSessionSchema.index({ "players.playerId": 1, createdAt: -1 });
gameSessionSchema.index({ status: 1, updatedAt: -1 });
gameSessionSchema.index({ roomCode: 1 }, { unique: true, sparse: true });

export const GameSessionModel: Model<GameSessionDocument> =
  mongoose.models.GameSession ??
  mongoose.model<GameSessionDocument>("GameSession", gameSessionSchema);
