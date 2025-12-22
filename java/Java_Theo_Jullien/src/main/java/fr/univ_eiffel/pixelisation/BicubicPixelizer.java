package fr.univ_eiffel.pixelisation;

import java.awt.image.BufferedImage;

public class BicubicPixelizer implements PixelizationMethod {
    @Override
    public void pixelize(BufferedImage source, BufferedImage destination) {
        int sourceWidth = source.getWidth();
        int sourceHeight = source.getHeight();
        int destWidth = destination.getWidth();
        int destHeight = destination.getHeight();
        double scaleX = (double) sourceWidth / destWidth;
        double scaleY = (double) sourceHeight / destHeight;

        for (int y = 0; y < destHeight; y++) {
            for (int x = 0; x < destWidth; x++) {
                double srcX = x * scaleX;
                double srcY = y * scaleY;

                int x1 = (int)srcX;
                int y1 = (int)srcY;

                double dx = srcX - x1;
                double dy = srcY - y1;
            }
        }
    }
}
