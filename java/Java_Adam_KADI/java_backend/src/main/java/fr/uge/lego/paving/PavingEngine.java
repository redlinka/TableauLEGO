package fr.uge.lego.paving;

import fr.uge.lego.stock.StockItem;

import java.util.List;

/**
 * Abstraction of a tiling engine.
 */
public interface PavingEngine {
    List<PavingSolution> computePavings(PixelMatrix pixels, List<StockItem> stockItems) throws Exception;
}
