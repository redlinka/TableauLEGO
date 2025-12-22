package fr.uge.lego.brick;

import java.util.Objects;

/**
 * Colour identified by a human-readable name and a hex RGB code.
 */
public final class BrickColor {
    private final String name;
    private final String hexCode; // "RRGGBB"

    public BrickColor(String name, String hexCode) {
        this.name = Objects.requireNonNull(name);
        this.hexCode = Objects.requireNonNull(hexCode).toUpperCase();
    }

    public String name() { return name; }
    public String hexCode() { return hexCode; }

    @Override
    public String toString() {
        return name + "(" + hexCode + ")";
    }
}
