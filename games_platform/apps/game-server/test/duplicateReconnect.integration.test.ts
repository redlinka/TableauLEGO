import { describe, expect, it } from "vitest";
import { createDuplicateHarness, WsTestClient } from "./helpers/duplicateHarness.js";

async function prepareStartedRoom(
  hostClient: WsTestClient,
  guestClient: WsTestClient
) {
  hostClient.send({
    type: "room.create",
    payload: {
      gameType: "line_breaker",
      mode: "duplicate_2p",
      seed: "duplicate-reconnect-seed"
    }
  });
  const created = await hostClient.waitFor("room.created");

  guestClient.send({
    type: "room.join",
    payload: {
      roomCode: created.payload.roomCode
    }
  });
  const joined = await guestClient.waitFor("room.joined");

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
  await hostClient.waitFor("game.started");

  return joined.payload;
}

describe("duplicate reconnect flow", () => {
  it("allows a player to resume within the grace window", async () => {
    const harness = await createDuplicateHarness({
      duplicateTurnLimitMs: 180,
      duplicateReconnectGraceMs: 400
    });
    try {
      const hostAuth = await harness.createGuest("Host-Reconnect-1");
      const guestAuth = await harness.createGuest("Guest-Reconnect-1");
      const hostClient = await harness.connectClient(hostAuth.accessToken);
      const guestClient = await harness.connectClient(guestAuth.accessToken);
      const joinedPayload = await prepareStartedRoom(hostClient, guestClient);

      await guestClient.terminate();

      const disconnected = await hostClient.waitFor("game.player_disconnected");

      expect(disconnected.payload.playerId).toBe(guestAuth.player.playerId);

      const resumedClient = await harness.connectClient(guestAuth.accessToken);
      resumedClient.send({
        type: "session.resume",
        requestId: "resume-guest",
        payload: {
          sessionId: joinedPayload.sessionId,
          resumeToken: joinedPayload.resumeToken
        }
      });
      const resumed = await resumedClient.waitFor(
        "session.resumed",
        (message) => message.requestId === "resume-guest"
      );

      expect(resumed.payload.state.session.sessionId).toBe(joinedPayload.sessionId);

      const reconnected = await hostClient.waitFor("game.player_reconnected");

      expect(reconnected.payload.playerId).toBe(guestAuth.player.playerId);
    } finally {
      await harness.close();
    }
  }, 300_000);

  it("declares a forfeit after the grace window expires", async () => {
    const harness = await createDuplicateHarness({
      duplicateTurnLimitMs: 180,
      duplicateReconnectGraceMs: 200
    });
    try {
      const hostAuth = await harness.createGuest("Host-Reconnect-2");
      const guestAuth = await harness.createGuest("Guest-Reconnect-2");
      const hostClient = await harness.connectClient(hostAuth.accessToken);
      const guestClient = await harness.connectClient(guestAuth.accessToken);

      await prepareStartedRoom(hostClient, guestClient);
      await guestClient.terminate();

      const finished = await hostClient.waitFor("game.finished", undefined, 5_000);

      expect(finished.payload.state.phase).toBe("finished");
      expect(
        finished.payload.state.result?.players.some((player) => player.outcome === "forfeit")
      ).toBe(true);
    } finally {
      await harness.close();
    }
  }, 300_000);
});
