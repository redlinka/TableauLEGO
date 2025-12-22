package fr.uge.lego.brick;

import java.util.Objects;

/**
 * Describes a brick geometry (width x length) without colour.
 */
public final class BrickType {
    private final String name;
    private final int width;
    private final int length;

    public BrickType(String name, int width, int length) {
        this.name = Objects.requireNonNull(name);
        this.width = width;
        this.length = length;
    }

    public String name() { return name; }
    public int width() { return width; }
    public int length() { return length; }

    @Override
    public String toString() {
        return name + "(" + width + "x" + length + ")";
    }
}
