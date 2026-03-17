import {
  HistoryDetailResponse,
  HistoryResponse,
  INITIAL_BRICK_SHAPES,
  LoyaltyBalanceResponse,
  LoyaltyLedgerResponse,
  SoloLineBreakerSessionResponse,
  SubmitSoloLineBreakerPlacementResponse
} from "@games-platform/game-contracts";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import {
  hydrateBoardState,
  listValidPlacementsForShape
} from "../src/domain/common/boardEngine.js";
import { createDuplicateHarness } from "./helpers/duplicateHarness.js";

describe("history and loyalty routes", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let accessToken = "";

  beforeAll(async () => {
    harness = await createDuplicateHarness({
      lineBreakerMaxSequenceLength: 5
    });

    const auth = await harness.createGuest("History-Loyalty");
    accessToken = auth.accessToken;
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("persists reward points and exposes enriched history detail", async () => {
    const createResponse = await harness.app.inject({
      method: "POST",
      url: "/api/v1/solo/line-breaker/sessions",
      headers: {
        authorization: `Bearer ${accessToken}`
      },
      payload: {
        seed: "phase-e-history-seed"
      }
    });
    let currentState = (createResponse.json() as SoloLineBreakerSessionResponse).state;

    expect(createResponse.statusCode).toBe(201);

    let safetyCounter = 0;

    while (!currentState.finished && currentState.currentOffer && safetyCounter < 12) {
      const shape = INITIAL_BRICK_SHAPES.find(
        (entry) => entry.shapeId === currentState.currentOffer?.shapeId
      );

      expect(shape).toBeDefined();

      const placements = listValidPlacementsForShape(
        hydrateBoardState(currentState.board),
        shape!
      );

      expect(placements.length).toBeGreaterThan(0);

      const placementResponse = await harness.app.inject({
        method: "POST",
        url: `/api/v1/solo/line-breaker/sessions/${currentState.session.sessionId}/placements`,
        headers: {
          authorization: `Bearer ${accessToken}`
        },
        payload: {
          anchorX: placements[0].anchorX,
          anchorY: placements[0].anchorY,
          rotation: placements[0].rotation
        }
      });
      const placed = placementResponse.json() as SubmitSoloLineBreakerPlacementResponse;

      expect(placementResponse.statusCode).toBe(200);
      expect(placed.accepted).toBe(true);

      currentState = placed.state;
      safetyCounter += 1;
    }

    expect(currentState.finished).toBe(true);

    const historyResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/history?gameType=line_breaker&mode=solo&limit=10",
      headers: {
        authorization: `Bearer ${accessToken}`
      }
    });
    const history = historyResponse.json() as HistoryResponse;

    expect(historyResponse.statusCode).toBe(200);
    expect(history.filters).toMatchObject({
      gameType: "line_breaker",
      mode: "solo",
      limit: 10
    });
    expect(history.total).toBeGreaterThanOrEqual(1);
    expect(history.items[0]).toMatchObject({
      sessionId: currentState.session.sessionId,
      gameType: "line_breaker",
      mode: "solo"
    });
    expect(history.items[0]?.rewardPoints).toBeGreaterThan(0);
    expect(history.items[0]?.durationMs).toBeGreaterThanOrEqual(0);
    expect(typeof history.items[0]?.finishReason).toBe("string");

    const historyDetailResponse = await harness.app.inject({
      method: "GET",
      url: `/api/v1/history/${history.items[0]?.historyId}`,
      headers: {
        authorization: `Bearer ${accessToken}`
      }
    });
    const historyDetail = historyDetailResponse.json() as HistoryDetailResponse;

    expect(historyDetailResponse.statusCode).toBe(200);
    expect(historyDetail.item.historyId).toBe(history.items[0]?.historyId);
    expect(historyDetail.session?.sessionId).toBe(currentState.session.sessionId);
    expect(historyDetail.result).toBeTruthy();
    expect(historyDetail.loyaltyEntries).toHaveLength(1);
    expect(historyDetail.loyaltyEntries[0]?.pointsDelta).toBe(
      historyDetail.item.rewardPoints
    );

    const balanceResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/loyalty/balance",
      headers: {
        authorization: `Bearer ${accessToken}`
      }
    });
    const balance = balanceResponse.json() as LoyaltyBalanceResponse;

    expect(balanceResponse.statusCode).toBe(200);
    expect(balance.summary.balance).toBe(historyDetail.item.rewardPoints);
    expect(balance.summary.totalEntries).toBe(1);
    expect(balance.recentEntries[0]?.sessionId).toBe(currentState.session.sessionId);

    const ledgerResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/loyalty/ledger?limit=10",
      headers: {
        authorization: `Bearer ${accessToken}`
      }
    });
    const ledger = ledgerResponse.json() as LoyaltyLedgerResponse;

    expect(ledgerResponse.statusCode).toBe(200);
    expect(ledger.total).toBe(1);
    expect(ledger.items[0]).toMatchObject({
      sessionId: currentState.session.sessionId,
      gameType: "line_breaker",
      mode: "solo"
    });
    expect(ledger.summary.balance).toBe(balance.summary.balance);
  }, 120_000);
});
