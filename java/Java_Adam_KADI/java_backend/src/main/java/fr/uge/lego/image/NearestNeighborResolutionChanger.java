package fr.uge.lego.image;

import java.awt.image.BufferedImage;

/**
 * Simple nearest neighbour interpolation.
 */
public final class NearestNeighborResolutionChanger implements ResolutionChanger {

    @Override
    public void convert(BufferedImage source, BufferedImage destination) {
        double xRatio = (double) source.getWidth() / destination.getWidth();
        double yRatio = (double) source.getHeight() / destination.getHeight();
        for (int y = 0; y < destination.getHeight(); y++) {
            for (int x = 0; x < destination.getWidth(); x++) {
                int srcX = (int) (x * xRatio);
                int srcY = (int) (y * yRatio);
                destination.setRGB(x, y, source.getRGB(srcX, srcY));
            }
        }
    }
}
