import mongoose from "mongoose";
import { AppConfig } from "../../config/env.js";

export async function connectMongoose(config: AppConfig): Promise<void> {
  mongoose.set("strictQuery", true);

  if (mongoose.connection.readyState !== 0) {
    await mongoose.disconnect();
  }

  await mongoose.connect(config.mongodbUri, {
    dbName: config.mongodbDbName
  });
}

export async function disconnectMongoose(): Promise<void> {
  await mongoose.disconnect();
}

export function getMongooseState():
  | "disconnected"
  | "connected"
  | "connecting"
  | "disconnecting" {
  switch (mongoose.connection.readyState) {
    case 0:
      return "disconnected";
    case 1:
      return "connected";
    case 2:
      return "connecting";
    case 3:
      return "disconnecting";
    default:
      return "disconnected";
  }
}
