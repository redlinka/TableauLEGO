import { GameSessionSummary } from "@games-platform/game-contracts";
import { GameSessionDocument, GameSessionModel } from "../models/GameSessionModel.js";

function toSummary(document: GameSessionDocument): GameSessionSummary {
  return {
    sessionId: document.sessionId,
    mode: document.mode,
    gameType: document.gameType,
    status: document.status,
    roomCode: document.roomCode,
    hostPlayerId: document.hostPlayerId,
    createdAt: document.createdAt.toISOString(),
    startedAt: document.startedAt?.toISOString(),
    endedAt: document.endedAt?.toISOString()
  };
}

export class GameSessionRepository {
  async create(document: Omit<GameSessionDocument, "createdAt" | "updatedAt">): Promise<void> {
    await GameSessionModel.create(document);
  }

  async findDocumentBySessionId(sessionId: string): Promise<GameSessionDocument | null> {
    return GameSessionModel.findOne({ sessionId }).lean<GameSessionDocument | null>();
  }

  async findDocumentByRoomCode(roomCode: string): Promise<GameSessionDocument | null> {
    return GameSessionModel.findOne({ roomCode }).lean<GameSessionDocument | null>();
  }

  async findOwnedSessionDocument(
    sessionId: string,
    playerId: string
  ): Promise<GameSessionDocument | null> {
    const found = await GameSessionModel.findOne({
      sessionId,
      "players.playerId": playerId
    }).lean<GameSessionDocument | null>();

    return found;
  }

  async findBySessionId(sessionId: string): Promise<GameSessionSummary | null> {
    const found = await GameSessionModel.findOne({ sessionId }).lean<GameSessionDocument | null>();

    return found ? toSummary(found) : null;
  }

  async upsert(
    document: Partial<GameSessionDocument> & Pick<GameSessionDocument, "sessionId">
  ): Promise<void> {
    await GameSessionModel.updateOne(
      { sessionId: document.sessionId },
      {
        $set: document
      },
      {
        upsert: true
      }
    );
  }

  async saveDocument(document: GameSessionDocument): Promise<void> {
    const {
      sessionId,
      createdAt: _createdAt,
      updatedAt: _updatedAt,
      ...updatable
    } = document;

    await GameSessionModel.updateOne(
      { sessionId },
      {
        $set: updatable
      }
    );
  }

  async saveSnapshot(input: {
    sessionId: string;
    currentTurn: number;
    status: GameSessionDocument["status"];
    sessionState: unknown;
    result?: unknown;
    startedAt?: Date;
    endedAt?: Date;
    sharedClock?: GameSessionDocument["sharedClock"];
    playerScore?: number;
  }): Promise<void> {
    await GameSessionModel.updateOne(
      { sessionId: input.sessionId },
      {
        $set: {
          currentTurn: input.currentTurn,
          status: input.status,
          sessionState: input.sessionState,
          result: input.result,
          startedAt: input.startedAt,
          endedAt: input.endedAt,
          sharedClock: input.sharedClock,
          "players.0.score": input.playerScore
        }
      }
    );
  }
}
