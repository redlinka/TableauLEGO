package fr.uge.lego.paving;

import java.math.BigDecimal;
import java.util.Collections;
import java.util.List;

/**
 * Represents a tiling solution returned by the C program.
 */
public final class PavingSolution {
    private final List<PlacedBrick> bricks;
    private final double qualityScore;
    private final BigDecimal totalPrice;

    public PavingSolution(List<PlacedBrick> bricks, double qualityScore, BigDecimal totalPrice) {
        this.bricks = List.copyOf(bricks);
        this.qualityScore = qualityScore;
        this.totalPrice = totalPrice;
    }

    public List<PlacedBrick> bricks() {
        return Collections.unmodifiableList(bricks);
    }

    public double qualityScore() {
        return qualityScore;
    }

    public BigDecimal totalPrice() {
        return totalPrice;
    }
}
