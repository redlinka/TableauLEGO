export interface SharedColorDefinition {
  color: string;
  hex: string;
  label: string;
}

export const LEGO_LIKE_PALETTE: SharedColorDefinition[] = [
  { color: "white", hex: "#F4F4F4", label: "White" },
  { color: "black", hex: "#1B1B1B", label: "Black" },
  { color: "light_gray", hex: "#B0B7C1", label: "Light Gray" },
  { color: "dark_gray", hex: "#5A6572", label: "Dark Gray" },
  { color: "red", hex: "#C91A09", label: "Red" },
  { color: "orange", hex: "#FE8A18", label: "Orange" },
  { color: "yellow", hex: "#F2CD37", label: "Yellow" },
  { color: "green", hex: "#237841", label: "Green" },
  { color: "blue", hex: "#0055BF", label: "Blue" },
  { color: "light_blue", hex: "#7DBFDD", label: "Light Blue" },
  { color: "tan", hex: "#DEC69C", label: "Tan" },
  { color: "brown", hex: "#6B4423", label: "Brown" }
];

export const LEGO_LIKE_PALETTE_MAP = Object.fromEntries(
  LEGO_LIKE_PALETTE.map((entry) => [entry.color, entry.hex])
) as Record<string, string>;

export const LINE_BREAKER_COLOR_POOL: Array<SharedColorDefinition & { weight: number }> = [
  { color: "red", hex: "#C91A09", label: "Red", weight: 24 },
  { color: "yellow", hex: "#F2CD37", label: "Yellow", weight: 22 },
  { color: "green", hex: "#237841", label: "Green", weight: 22 },
  { color: "blue", hex: "#0055BF", label: "Blue", weight: 22 },
  { color: "orange", hex: "#FE8A18", label: "Orange", weight: 18 }
];
