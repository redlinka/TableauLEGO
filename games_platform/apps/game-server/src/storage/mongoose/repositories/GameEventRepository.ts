import { randomUUID } from "node:crypto";
import { EventType } from "@games-platform/game-contracts";
import { GameEventModel } from "../models/GameEventModel.js";

export class GameEventRepository {
  async append(input: {
    sessionId: string;
    seq: number;
    type: EventType;
    actorPlayerId?: string;
    payload?: Record<string, unknown>;
  }): Promise<void> {
    await GameEventModel.create({
      eventId: `evt_${randomUUID()}`,
      ...input
    });
  }
}

