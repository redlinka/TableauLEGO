import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness, WsTestClient } from "./helpers/duplicateHarness.js";

describe("duplicate room system", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let hostClient: WsTestClient;
  let guestClient: WsTestClient;

  beforeAll(async () => {
    harness = await createDuplicateHarness();
    const hostAuth = await harness.createGuest("Host-D");
    const guestAuth = await harness.createGuest("Guest-D");
    hostClient = await harness.connectClient(hostAuth.accessToken);
    guestClient = await harness.connectClient(guestAuth.accessToken);
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("creates a room, joins it, marks players ready and starts the match", async () => {
    hostClient.send({
      type: "room.create",
      requestId: "room-create",
      payload: {
        gameType: "line_breaker",
        mode: "duplicate_2p"
      }
    });
    const created = await hostClient.waitFor(
      "room.created",
      (message) => message.requestId === "room-create"
    );

    expect(created.payload.state.phase).toBe("waiting");
    expect(created.payload.state.players).toHaveLength(1);

    guestClient.send({
      type: "room.join",
      requestId: "room-join",
      payload: {
        roomCode: created.payload.roomCode
      }
    });
    const joined = await guestClient.waitFor(
      "room.joined",
      (message) => message.requestId === "room-join"
    );

    expect(joined.payload.state.players).toHaveLength(2);

    const joinedBroadcast = await hostClient.waitFor(
      "room.player_joined",
      (message) => message.payload.player.playerId === joined.payload.state.players[1].playerId
    );

    expect(joinedBroadcast.payload.state.players).toHaveLength(2);

    hostClient.send({
      type: "room.set_ready",
      requestId: "host-ready",
      payload: {
        ready: true
      }
    });
    guestClient.send({
      type: "room.set_ready",
      requestId: "guest-ready",
      payload: {
        ready: true
      }
    });

    const readyChanged = await hostClient.waitFor(
      "room.player_ready_changed",
      (message) => message.payload.ready === true && message.payload.state.phase === "ready"
    );

    expect(readyChanged.payload.state.phase).toBe("ready");

    hostClient.send({
      type: "room.start",
      requestId: "start-room",
      payload: {}
    });

    const started = await hostClient.waitFor(
      "game.started",
      (message) => message.requestId === "start-room"
    );
    const guestStarted = await guestClient.waitFor("game.started");

    expect(started.payload.state.phase).toBe("in_game");
    expect(guestStarted.payload.state.currentOffer?.offerId).toBe(
      started.payload.state.currentOffer?.offerId
    );
    expect(started.payload.state.turn.index).toBe(1);
  }, 120_000);
});
