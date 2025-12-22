package fr.univ_eiffel.pixelisation;

import java.awt.image.BufferedImage;
import java.util.ArrayList;

public class BilinearPixelizer implements PixelizationMethod {
    /**
     * Bilinear interpolation
     */
    @Override
    public void pixelize(BufferedImage source, BufferedImage destination) {
        int sourceWidth = source.getWidth();
        int sourceHeight = source.getHeight();
        int destWidth = destination.getWidth();
        int destHeight = destination.getHeight();
        float scaleX = sourceWidth / (float)destWidth;
        float scaleY = sourceHeight / (float)destHeight;

        for (int y = 0; y < destHeight; y++) {
            for (int x = 0; x < destWidth; x++) {
                // Real position in the source image
                float srcX = x * scaleX;
                float srcY = y * scaleY;

                int x1 = (int)srcX;
                int y1 = (int)srcY;
                int x2 = Math.min(x1 + 1, sourceWidth - 1); // avoid exceeding
                int y2 = Math.min(y1 + 1, sourceHeight - 1); // avoid exceeding

                int pixel1 = source.getRGB(x1, y1);
                int pixel2 = source.getRGB(x2, y1);
                int pixel3 = source.getRGB(x1, y2);
                int pixel4 = source.getRGB(x2, y2);

                float dx = srcX - x1;
                float dy = srcY - y1;

                // Bilinear interpolation
                int r = (int)(
                        (1 - dx - dy + dx * dy) * ((pixel1 >> 16) & 0xff) +
                                dx * (1 - dy) * ((pixel2 >> 16) & 0xff) +
                                dy * (1 - dx) * ((pixel3 >> 16) & 0xff) +
                                dx * dy * ((pixel4 >> 16) & 0xff)
                );

                int g = (int)(
                        (1 - dx - dy + dx * dy) * ((pixel1 >> 8) & 0xff) +
                                dx * (1 - dy) * ((pixel2 >> 8) & 0xff) +
                                dy * (1 - dx) * ((pixel3 >> 8) & 0xff) +
                                dx * dy * ((pixel4 >> 8) & 0xff)
                );

                int b = (int)(
                        (1 - dx - dy + dx * dy) * (pixel1 & 0xff) +
                                dx * (1 - dy) * (pixel2 & 0xff) +
                                dy * (1 - dx) * (pixel3 & 0xff) +
                                dx * dy * (pixel4 & 0xff)
                );
                int rgb = (r << 16) | (g << 8) | b;
                destination.setRGB(x, y, rgb);
            }
        }
    }
}

