import { randomUUID } from "node:crypto";
import { ChatMessageView } from "@games-platform/game-contracts";
import { ChatMessageDocument, ChatMessageModel } from "../models/ChatMessageModel.js";

function toView(document: ChatMessageDocument): ChatMessageView {
  return {
    messageId: document.messageId,
    sessionId: document.sessionId,
    playerId: document.playerId,
    publicAlias: document.publicAlias,
    content: document.content,
    createdAt: document.createdAt.toISOString()
  };
}

export class ChatMessageRepository {
  async append(input: {
    sessionId: string;
    playerId: string;
    publicAlias: string;
    content: string;
  }): Promise<void> {
    await ChatMessageModel.create({
      messageId: `msg_${randomUUID()}`,
      ...input
    });
  }

  async listBySessionId(sessionId: string, limit = 20): Promise<ChatMessageView[]> {
    const rows = await ChatMessageModel.find({ sessionId })
      .sort({ createdAt: -1 })
      .limit(limit)
      .lean<ChatMessageDocument[]>();

    return rows.reverse().map(toView);
  }
}
