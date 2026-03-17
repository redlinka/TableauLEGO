import mongoose, { Model } from "mongoose";

export interface ChatMessageDocument {
  messageId: string;
  sessionId: string;
  playerId: string;
  publicAlias: string;
  content: string;
  createdAt: Date;
}

const chatMessageSchema = new mongoose.Schema<ChatMessageDocument>(
  {
    messageId: {
      type: String,
      required: true,
      unique: true
    },
    sessionId: {
      type: String,
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
    content: {
      type: String,
      required: true
    },
    createdAt: {
      type: Date,
      default: () => new Date()
    }
  },
  {
    collection: "chat_messages",
    versionKey: false
  }
);

chatMessageSchema.index({ sessionId: 1, createdAt: 1 });

export const ChatMessageModel: Model<ChatMessageDocument> =
  mongoose.models.ChatMessage ??
  mongoose.model<ChatMessageDocument>("ChatMessage", chatMessageSchema);
