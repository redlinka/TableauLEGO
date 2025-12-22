package fr.uge.lego.stock;

import fr.uge.lego.brick.BrickColor;
import fr.uge.lego.brick.BrickType;

import java.math.BigDecimal;
import java.util.Objects;

/**
 * Stock entry: type + colour + quantity + unit price.
 */
public final class StockItem {
    private final BrickType type;
    private final BrickColor color;
    private final long quantity;
    private final BigDecimal unitPrice;

    public StockItem(BrickType type, BrickColor color, long quantity, BigDecimal unitPrice) {
        this.type = Objects.requireNonNull(type);
        this.color = Objects.requireNonNull(color);
        this.quantity = quantity;
        this.unitPrice = Objects.requireNonNull(unitPrice);
    }

    public BrickType type() { return type; }
    public BrickColor color() { return color; }
    public long quantity() { return quantity; }
    public BigDecimal unitPrice() { return unitPrice; }
}
