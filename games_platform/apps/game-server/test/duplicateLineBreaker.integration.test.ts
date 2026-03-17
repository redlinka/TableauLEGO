import { DuplicateRoomState, INITIAL_BRICK_SHAPES } from "@games-platform/game-contracts";
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { hydrateBoardState, listValidPlacementsForShape } from "../src/domain/common/boardEngine.js";
import { createDuplicateHarness, WsTestClient } from "./helpers/duplicateHarness.js";

function getPlayerState(state: DuplicateRoomState, playerId: string) {
  const player = state.players.find((entry) => entry.playerId === playerId);

  if (!player) {
    throw new Error(`Missing player '${playerId}' in duplicate state.`);
  }

  return player;
}

describe("duplicate line_breaker", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let hostClient: WsTestClient;
  let guestClient: WsTestClient;
  let hostId = "";
  let guestId = "";

  beforeAll(async () => {
    harness = await createDuplicateHarness({
      lineBreakerMaxSequenceLength: 2
    });
    const hostAuth = await harness.createGuest("Host-Line");
    const guestAuth = await harness.createGuest("Guest-Line");
    hostId = hostAuth.player.playerId;
    guestId = guestAuth.player.playerId;
    hostClient = await harness.connectClient(hostAuth.accessToken);
    guestClient = await harness.connectClient(guestAuth.accessToken);
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("uses the same sequence while keeping scores independent", async () => {
    hostClient.send({
      type: "room.create",
      requestId: "line-create",
      payload: {
        gameType: "line_breaker",
        mode: "duplicate_2p",
        seed: "duplicate-line-seed"
      }
    });
    const created = await hostClient.waitFor(
      "room.created",
      (message) => message.requestId === "line-create"
    );

    guestClient.send({
      type: "room.join",
      payload: {
        roomCode: created.payload.roomCode
      }
    });
    await guestClient.waitFor("room.joined");

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
    await hostClient.waitFor("room.player_ready_changed", (message) => message.payload.state.phase === "ready");

    hostClient.send({
      type: "room.start",
      payload: {}
    });
    let state = (await hostClient.waitFor("game.started")).payload.state;
    const firstOfferId = state.currentOffer?.offerId;

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
          requestId: "guest-invalid",
          payload: {
            sessionId: state.session.sessionId,
            offerId: offer.offerId,
            anchorX: 99,
            anchorY: 99,
            rotation: 0
          }
        });
        const rejected = await guestClient.waitFor(
          "game.action_rejected",
          (message) => message.requestId === "guest-invalid"
        );

        expect(rejected.payload.reason).toBe("out_of_bounds");
      }

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
          anchorX: guestPlacements[0].anchorX,
          anchorY: guestPlacements[0].anchorY,
          rotation: guestPlacements[0].rotation
        }
      });

      const resolved = await hostClient.waitFor(
        "game.turn_resolved",
        (message) => message.payload.resolution.turnIndex === state.turn.index
      );
      state = resolved.payload.state;
    }

    expect(firstOfferId).toBeTruthy();
    expect(state.phase).toBe("finished");
    expect(state.result?.players).toHaveLength(2);

    const hostResult = state.result?.players.find((player) => player.playerId === hostId);
    const guestResult = state.result?.players.find((player) => player.playerId === guestId);

    expect(hostResult?.score).not.toBe(guestResult?.score);
  }, 120_000);
});
