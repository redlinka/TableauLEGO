import {
  DuplicateRoomState,
  HistoryResponse,
  INITIAL_BRICK_SHAPES,
  LoyaltyBalanceResponse
} from "@games-platform/game-contracts";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import {
  hydrateBoardState,
  listValidPlacementsForShape
} from "../src/domain/common/boardEngine.js";
import { createDuplicateHarness, WsTestClient } from "./helpers/duplicateHarness.js";

function getPlayerState(state: DuplicateRoomState, playerId: string) {
  const player = state.players.find((entry) => entry.playerId === playerId);

  if (!player) {
    throw new Error(`Missing player '${playerId}' in duplicate state.`);
  }

  return player;
}

describe("duplicate loyalty integration", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let hostClient: WsTestClient;
  let guestClient: WsTestClient;
  let hostId = "";
  let guestId = "";
  let hostToken = "";
  let guestToken = "";

  beforeAll(async () => {
    harness = await createDuplicateHarness({
      lineBreakerMaxSequenceLength: 2
    });

    const hostAuth = await harness.createGuest("Host-Loyalty");
    const guestAuth = await harness.createGuest("Guest-Loyalty");
    hostId = hostAuth.player.playerId;
    guestId = guestAuth.player.playerId;
    hostToken = hostAuth.accessToken;
    guestToken = guestAuth.accessToken;
    hostClient = await harness.connectClient(hostToken);
    guestClient = await harness.connectClient(guestToken);
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("writes history and loyalty ledger entries for both duplicate players", async () => {
    hostClient.send({
      type: "room.create",
      requestId: "loyalty-create",
      payload: {
        gameType: "line_breaker",
        mode: "duplicate_2p",
        seed: "duplicate-loyalty-seed"
      }
    });
    const created = await hostClient.waitFor(
      "room.created",
      (message) => message.requestId === "loyalty-create"
    );

    guestClient.send({
      type: "room.join",
      requestId: "loyalty-join",
      payload: {
        roomCode: created.payload.roomCode
      }
    });
    await guestClient.waitFor("room.joined", (message) => message.requestId === "loyalty-join");

    hostClient.send({
      type: "room.set_ready",
      payload: {
        ready: true
      }
    });
    guestClient.send({
      type: "room.set_ready",
      payload: {
        ready: true
      }
    });
    await hostClient.waitFor(
      "room.player_ready_changed",
      (message) => message.payload.state.phase === "ready"
    );

    hostClient.send({
      type: "room.start",
      requestId: "loyalty-start",
      payload: {}
    });
    let state = (
      await hostClient.waitFor(
        "game.started",
        (message) => message.requestId === "loyalty-start"
      )
    ).payload.state;

    while (state.phase !== "finished" && state.currentOffer) {
      const offer = state.currentOffer;
      const shape = INITIAL_BRICK_SHAPES.find((entry) => entry.shapeId === offer.shapeId);

      expect(shape).toBeDefined();

      const hostPlacements = listValidPlacementsForShape(
        hydrateBoardState(getPlayerState(state, hostId).board),
        shape!
      );
      const guestPlacements = listValidPlacementsForShape(
        hydrateBoardState(getPlayerState(state, guestId).board),
        shape!
      );

      expect(hostPlacements.length).toBeGreaterThan(0);
      expect(guestPlacements.length).toBeGreaterThan(0);

      if (state.turn.index === 1) {
        guestClient.send({
          type: "game.place_brick",
          requestId: "loyalty-invalid",
          payload: {
            sessionId: state.session.sessionId,
            offerId: offer.offerId,
            anchorX: 99,
            anchorY: 99,
            rotation: 0
          }
        });
        await guestClient.waitFor(
          "game.action_rejected",
          (message) => message.requestId === "loyalty-invalid"
        );
      }

      const guestPlacement =
        guestPlacements[guestPlacements.length - 1] ?? guestPlacements[0];

      hostClient.send({
        type: "game.place_brick",
        payload: {
          sessionId: state.session.sessionId,
          offerId: offer.offerId,
          anchorX: hostPlacements[0].anchorX,
          anchorY: hostPlacements[0].anchorY,
          rotation: hostPlacements[0].rotation
        }
      });
      guestClient.send({
        type: "game.place_brick",
        payload: {
          sessionId: state.session.sessionId,
          offerId: offer.offerId,
          anchorX: guestPlacement.anchorX,
          anchorY: guestPlacement.anchorY,
          rotation: guestPlacement.rotation
        }
      });

      const resolved = await hostClient.waitFor(
        "game.turn_resolved",
        (message) => message.payload.resolution.turnIndex === state.turn.index
      );
      state = resolved.payload.state;
    }

    expect(state.phase).toBe("finished");
    expect(state.result?.players).toHaveLength(2);

    const hostHistoryResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/history?mode=duplicate_2p&limit=10",
      headers: {
        authorization: `Bearer ${hostToken}`
      }
    });
    const hostHistory = hostHistoryResponse.json() as HistoryResponse;
    const guestHistoryResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/history?mode=duplicate_2p&limit=10",
      headers: {
        authorization: `Bearer ${guestToken}`
      }
    });
    const guestHistory = guestHistoryResponse.json() as HistoryResponse;

    expect(hostHistoryResponse.statusCode).toBe(200);
    expect(guestHistoryResponse.statusCode).toBe(200);
    expect(hostHistory.items[0]).toMatchObject({
      sessionId: state.session.sessionId,
      mode: "duplicate_2p",
      gameType: "line_breaker"
    });
    expect(guestHistory.items[0]).toMatchObject({
      sessionId: state.session.sessionId,
      mode: "duplicate_2p",
      gameType: "line_breaker"
    });
    expect(hostHistory.items[0]?.rewardPoints).toBeGreaterThan(0);
    expect(guestHistory.items[0]?.rewardPoints).toBeGreaterThan(0);

    const hostBalanceResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/loyalty/balance",
      headers: {
        authorization: `Bearer ${hostToken}`
      }
    });
    const hostBalance = hostBalanceResponse.json() as LoyaltyBalanceResponse;
    const guestBalanceResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/loyalty/balance",
      headers: {
        authorization: `Bearer ${guestToken}`
      }
    });
    const guestBalance = guestBalanceResponse.json() as LoyaltyBalanceResponse;

    expect(hostBalanceResponse.statusCode).toBe(200);
    expect(guestBalanceResponse.statusCode).toBe(200);
    expect(hostBalance.summary.balance).toBe(hostHistory.items[0]?.rewardPoints);
    expect(guestBalance.summary.balance).toBe(guestHistory.items[0]?.rewardPoints);
  }, 120_000);
});
