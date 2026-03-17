export enum GameType {
  ImageRebuild = "image_rebuild",
  LineBreaker = "line_breaker"
}

export enum GameMode {
  Solo = "solo",
  Duplicate2P = "duplicate_2p"
}

export enum SessionStatus {
  Waiting = "waiting",
  Active = "active",
  Completed = "completed",
  Abandoned = "abandoned"
}

export enum ConnectionState {
  Connected = "connected",
  Disconnected = "disconnected",
  Reconnecting = "reconnecting"
}

export enum AuthSource {
  Guest = "guest",
  Php = "php"
}

export enum PlayerSeatType {
  Solo = "solo",
  Host = "host",
  Guest = "guest"
}

export enum EventType {
  SessionCreated = "session_created",
  SessionStarted = "session_started",
  BrickPlaced = "brick_placed",
  ChatSent = "chat_sent",
  SessionFinished = "session_finished",
  RoomCreated = "room_created",
  RoomJoined = "room_joined",
  RoomLeft = "room_left",
  ReadyChanged = "ready_changed",
  TurnStarted = "turn_started",
  TurnResolved = "turn_resolved",
  PlayerDisconnected = "player_disconnected",
  PlayerReconnected = "player_reconnected",
  PlayerForfeited = "player_forfeited"
}

export enum LoyaltyEntryType {
  SessionReward = "session_reward",
  DuplicateBonus = "duplicate_bonus",
  AdminAdjustment = "admin_adjustment"
}
