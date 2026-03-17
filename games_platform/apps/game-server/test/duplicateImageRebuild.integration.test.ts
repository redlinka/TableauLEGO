import {
  DuplicateRoomState,
  INITIAL_BRICK_SHAPES,
  PuzzleListResponse
} from "@games-platform/game-contracts";
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

describe("duplicate image_rebuild", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let hostClient: WsTestClient;
  let guestClient: WsTestClient;
  let hostId = "";
  let guestId = "";
  let puzzleId = "";

  beforeAll(async () => {
    harness = await createDuplicateHarness({
      imageRebuildMaxSequenceLength: 2
    });

    const puzzlesResponse = await harness.app.inject({
      method: "GET",
      url: "/api/v1/puzzles"
    });
    puzzleId = (puzzlesResponse.json() as PuzzleListResponse).items[0]?.id ?? "";

    const hostAuth = await harness.createGuest("Host-Image");
    const guestAuth = await harness.createGuest("Guest-Image");
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

  it("runs a duplicate image_rebuild match with a shared sequence and a final result", async () => {
    hostClient.send({
      type: "room.create",
      requestId: "image-create",
      payload: {
        gameType: "image_rebuild",
        mode: "duplicate_2p",
        puzzleId,
        seed: "duplicate-image-seed"
      }
    });
    const created = await hostClient.waitFor(
      "room.created",
      (message) => message.requestId === "image-create"
    );

    guestClient.send({
      type: "room.join",
      requestId: "image-join",
      payload: {
        roomCode: created.payload.roomCode
      }
    });
    await guestClient.waitFor("room.joined", (message) => message.requestId === "image-join");

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
      requestId: "image-start",
      payload: {}
    });
    let state = (await hostClient.waitFor(
      "game.started",
      (message) => message.requestId === "image-start"
    )).payload.state;

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

    expect(state.phase).toBe("finished");
    expect(state.result?.players).toHaveLength(2);
    expect(state.result?.players.every((player) => player.score >= 0)).toBe(true);
  }, 120_000);
});
