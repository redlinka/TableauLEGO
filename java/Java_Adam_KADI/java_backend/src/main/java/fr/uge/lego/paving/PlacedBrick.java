package fr.uge.lego.paving;

import fr.uge.lego.brick.BrickColor;
import fr.uge.lego.brick.BrickType;

/**
 * One brick placed on the final Lego board.
 */
public final class PlacedBrick {
    private final BrickType type;
    private final BrickColor color;
    private final int x;
    private final int y;

    public PlacedBrick(BrickType type, BrickColor color, int x, int y) {
        this.type = type;
        this.color = color;
        this.x = x;
        this.y = y;
    }

    public BrickType type() { return type; }
    public BrickColor color() { return color; }
    public int x() { return x; }
    public int y() { return y; }
}
