package fr.uge.lego.stock;

import fr.uge.lego.brick.BrickColor;
import fr.uge.lego.brick.BrickType;

import java.util.ArrayList;
import java.util.List;
import java.util.Optional;

/**
 * Simple in-memory implementation, useful for tests or small demos.
 * The real project can later plug a JDBC implementation.
 */
public final class InMemoryStockRepository implements StockRepository {

    private final List<StockItem> items = new ArrayList<>();

    public void add(StockItem item) {
        items.add(item);
    }

    @Override
    public List<StockItem> findAll() {
        return List.copyOf(items);
    }

    @Override
    public Optional<StockItem> findByTypeAndColor(BrickType type, BrickColor color) {
        return items.stream()
                .filter(it -> it.type().name().equals(type.name())
                           && it.color().hexCode().equalsIgnoreCase(color.hexCode()))
                .findFirst();
    }
}
