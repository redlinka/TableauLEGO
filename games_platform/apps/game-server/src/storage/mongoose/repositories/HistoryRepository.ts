import { HistoryEntry } from "@games-platform/game-contracts";
import { HistoryEntryDocument, HistoryEntryModel } from "../models/HistoryEntryModel.js";

function toHistoryEntry(document: HistoryEntryDocument): HistoryEntry {
  return {
    historyId: document.historyId,
    playerId: document.playerId,
    sessionId: document.sessionId,
    gameType: document.gameType,
    mode: document.mode,
    outcome: document.outcome,
    score: document.score,
    rewardPoints: document.rewardPoints,
    opponentSummary: document.opponentSummary,
    startedAt: document.startedAt.toISOString(),
    endedAt: document.endedAt.toISOString(),
    durationMs: Math.max(0, document.endedAt.getTime() - document.startedAt.getTime()),
    finishReason:
      typeof document.metadata?.finishReason === "string"
        ? document.metadata.finishReason
        : undefined,
    metadata: document.metadata
  };
}

export class HistoryRepository {
  async create(
    document: Omit<HistoryEntryDocument, "createdAt" | "updatedAt">
  ): Promise<boolean> {
    const result = await HistoryEntryModel.updateOne(
      { historyId: document.historyId },
      {
        $setOnInsert: document
      },
      {
        upsert: true
      }
    );

    return result.upsertedCount > 0;
  }

  async listByPlayerId(playerId: string, limit = 20): Promise<HistoryEntry[]> {
    const entries = await HistoryEntryModel.find({ playerId })
      .sort({ endedAt: -1 })
      .limit(limit)
      .lean<HistoryEntryDocument[]>();

    return entries.map(toHistoryEntry);
  }

  async listByPlayerIdFiltered(input: {
    playerId: string;
    limit: number;
    gameType?: HistoryEntryDocument["gameType"];
    mode?: HistoryEntryDocument["mode"];
  }): Promise<HistoryEntry[]> {
    const filter: Record<string, unknown> = {
      playerId: input.playerId
    };

    if (input.gameType) {
      filter.gameType = input.gameType;
    }

    if (input.mode) {
      filter.mode = input.mode;
    }

    const entries = await HistoryEntryModel.find(filter)
      .sort({ endedAt: -1 })
      .limit(input.limit)
      .lean<HistoryEntryDocument[]>();

    return entries.map(toHistoryEntry);
  }

  async findByHistoryId(playerId: string, historyId: string): Promise<HistoryEntry | null> {
    const found = await HistoryEntryModel.findOne({
      playerId,
      historyId
    }).lean<HistoryEntryDocument | null>();

    return found ? toHistoryEntry(found) : null;
  }
}
