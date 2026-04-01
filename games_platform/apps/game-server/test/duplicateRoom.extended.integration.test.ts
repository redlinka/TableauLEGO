import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { createDuplicateHarness, WsTestClient } from "./helpers/duplicateHarness.js";

describe("duplicate room system – extended", () => {
  let harness: Awaited<ReturnType<typeof createDuplicateHarness>>;
  let hostClient: WsTestClient;
  let guestClient: WsTestClient;

  beforeAll(async () => {
    harness = await createDuplicateHarness();
    const hostAuth = await harness.createGuest("Host-Ext");
    const guestAuth = await harness.createGuest("Guest-Ext");
    hostClient = await harness.connectClient(hostAuth.accessToken);
    guestClient = await harness.connectClient(guestAuth.accessToken);
  }, 300_000);

  afterAll(async () => {
    if (harness) {
      await harness.close();
    }
  }, 300_000);

  it("rejects joining a room with an invalid room code", async () => {
    guestClient.send({
      type: "room.join",
      requestId: "bad-code",
      payload: { roomCode: "ZZZZZZ" }
    });
    const error = await guestClient.waitFor(
      "error",
      (message) => message.requestId === "bad-code"
    );

    expect(error.payload.code).toBeTruthy();
  }, 120_000);

  it("prevents the guest from starting the room (host-only action)", async () => {
    hostClient.send({
      type: "room.create",
      requestId: "host-only-create",
      payload: { gameType: "line_breaker", mode: "duplicate_2p" }
    });
    const created = await hostClient.waitFor(
      "room.created",
      (message) => message.requestId === "host-only-create"
    );

    guestClient.send({
      type: "room.join",
      requestId: "host-only-join",
      payload: { roomCode: created.payload.roomCode }
    });
    await guestClient.waitFor(
      "room.joined",
      (message) => message.requestId === "host-only-join"
    );

    hostClient.send({
      type: "room.set_ready",
      payload: { ready: true }
    });
    guestClient.send({
      type: "room.set_ready",
      payload: { ready: true }
    });
    await hostClient.waitFor(
      "room.player_ready_changed",
      (message) => message.payload.state.phase === "ready"
    );

    guestClient.send({
      type: "room.start",
      requestId: "guest-tries-start",
      payload: {}
    });
    const error = await guestClient.waitFor(
      "error",
      (message) => message.requestId === "guest-tries-start"
    );

    expect(error.payload.code).toBeTruthy();
  }, 120_000);

  it("prevents starting when not all players are ready", async () => {
    const newHarness = await createDuplicateHarness();

    try {
      const h = await newHarness.createGuest("NotReady-Host");
      const g = await newHarness.createGuest("NotReady-Guest");
      const hClient = await newHarness.connectClient(h.accessToken);
      const gClient = await newHarness.connectClient(g.accessToken);

      hClient.send({
        type: "room.create",
        requestId: "nr-create",
        payload: { gameType: "line_breaker", mode: "duplicate_2p" }
      });
      const created = await hClient.waitFor(
        "room.created",
        (message) => message.requestId === "nr-create"
      );

      gClient.send({
        type: "room.join",
        requestId: "nr-join",
        payload: { roomCode: created.payload.roomCode }
      });
      await gClient.waitFor("room.joined", (message) => message.requestId === "nr-join");

      hClient.send({
        type: "room.set_ready",
        payload: { ready: true }
      });

      hClient.send({
        type: "room.start",
        requestId: "nr-start",
        payload: {}
      });
      const error = await hClient.waitFor(
        "error",
        (message) => message.requestId === "nr-start"
      );

      expect(error.payload.code).toBeTruthy();
    } finally {
      await newHarness.close();
    }
  }, 300_000);
});
