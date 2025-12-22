package fr.uge.lego.image;

import java.awt.image.BufferedImage;

/**
 * Basic bicubic interpolation using a 4x4 neighbourhood.
 * Not highly optimised but sufficient for the SAE.
 */
public final class BicubicResolutionChanger implements ResolutionChanger {

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

                double r = 0, g = 0, b = 0;
                for (int m = -1; m <= 2; m++) {
                    double wx = ColorUtils.cubicWeight(dx - m);
                    int sx = clamp(gxi + m, 0, source.getWidth() - 1);
                    for (int n = -1; n <= 2; n++) {
                        double wy = ColorUtils.cubicWeight(dy - n);
                        int sy = clamp(gyi + n, 0, source.getHeight() - 1);
                        double w = wx * wy;
                        int rgb = source.getRGB(sx, sy);
                        r += w * ((rgb >> 16) & 0xFF);
                        g += w * ((rgb >> 8) & 0xFF);
                        b += w * (rgb & 0xFF);
                    }
                }
                int ir = clamp((int) Math.round(r), 0, 255);
                int ig = clamp((int) Math.round(g), 0, 255);
                int ib = clamp((int) Math.round(b), 0, 255);
                int rgb = (ir << 16) | (ig << 8) | ib;
                destination.setRGB(x, y, rgb);
            }
        }
    }

    private static int clamp(int v, int min, int max) {
        return Math.max(min, Math.min(max, v));
    }
}
