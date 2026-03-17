import { LEGO_LIKE_PALETTE_MAP } from "@games-platform/game-contracts";

const CSS_COLOR_FALLBACKS: Record<string, string> = {
  light_gray: "#B0B7C1",
  dark_gray: "#5A6572",
  light_blue: "#7DBFDD"
};

export function resolveBoardColor(
  color: string | null | undefined,
  paletteColors?: Record<string, string>
): string | undefined {
  if (!color) {
    return undefined;
  }

  return paletteColors?.[color] ?? LEGO_LIKE_PALETTE_MAP[color] ?? CSS_COLOR_FALLBACKS[color] ?? color;
}
