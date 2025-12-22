package fr.uge.lego.stock;

import fr.uge.lego.brick.BrickColor;
import fr.uge.lego.brick.BrickType;

import java.util.List;
import java.util.Optional;

/**
 * Abstraction over stock access (DB, file, in-memory...).
 */
public interface StockRepository {
    List<StockItem> findAll() throws Exception;
    Optional<StockItem> findByTypeAndColor(BrickType type, BrickColor color) throws Exception;
}
