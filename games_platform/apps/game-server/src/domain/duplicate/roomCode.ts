const ROOM_ALPHABET = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
const ROOM_CODE_LENGTH = 6;

export function generateRoomCode(random = Math.random): string {
  let value = "";

  for (let index = 0; index < ROOM_CODE_LENGTH; index += 1) {
    const offset = Math.floor(random() * ROOM_ALPHABET.length);
    value += ROOM_ALPHABET[offset];
  }

  return value;
}
