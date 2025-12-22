package fr.uge.lego.image;

import java.awt.image.BufferedImage;

/**
 * Bilinear interpolation with 4 neighbouring pixels.
 */
public final class BilinearResolutionChanger implements ResolutionChanger {

    @Override
    public void convert(BufferedImage source, BufferedImage destination) {
        double xRatio = (double) (source.getWidth() - 1) / destination.getWidth();
        double yRatio = (double) (source.getHeight() - 1) / destination.getHeight();

        for (int y = 0; y < destination.getHeight(); y++) {
            for (int x = 0; x < destination.getWidth(); x++) {
                double gx = x * xRatio;
                double gy = y * yRatio;
                int gxi = (int) gx;
                int gyi = (int) gy;
                double dx = gx - gxi;
                double dy = gy - gyi;

                int rgb00 = source.getRGB(gxi, gyi);
                int rgb10 = source.getRGB(Math.min(gxi + 1, source.getWidth() - 1), gyi);
                int rgb01 = source.getRGB(gxi, Math.min(gyi + 1, source.getHeight() - 1));
                int rgb11 = source.getRGB(Math.min(gxi + 1, source.getWidth() - 1),
                                           Math.min(gyi + 1, source.getHeight() - 1));

                int r = (int) ColorUtils.bilinear(
                        (rgb00 >> 16) & 0xFF,
                        (rgb10 >> 16) & 0xFF,
                        (rgb01 >> 16) & 0xFF,
                        (rgb11 >> 16) & 0xFF,
                        dx, dy);
                int g = (int) ColorUtils.bilinear(
                        (rgb00 >> 8) & 0xFF,
                        (rgb10 >> 8) & 0xFF,
                        (rgb01 >> 8) & 0xFF,
                        (rgb11 >> 8) & 0xFF,
                        dx, dy);
                int b = (int) ColorUtils.bilinear(
                        rgb00 & 0xFF,
                        rgb10 & 0xFF,
                        rgb01 & 0xFF,
                        rgb11 & 0xFF,
                        dx, dy);

                int rgb = (r << 16) | (g << 8) | b;
                destination.setRGB(x, y, rgb);
            }
        }
    }
}
