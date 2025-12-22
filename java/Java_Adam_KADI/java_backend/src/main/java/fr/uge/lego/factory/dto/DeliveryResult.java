package fr.uge.lego.factory.dto;

import java.util.List;

/**
 * Result of GET /ordering/deliver/:quote_id.
 */
public final class DeliveryResult {
    public String completion_date;
    public List<BuiltBlock> built_blocks;
    public List<PendingBlock> pending_blocks;

    @Override
    public String toString() {
        return "DeliveryResult{completion_date='" + completion_date + "', built=" +
                built_blocks + ", pending=" + pending_blocks + "}";
    }
}
