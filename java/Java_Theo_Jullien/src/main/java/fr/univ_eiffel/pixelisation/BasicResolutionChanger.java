package fr.univ_eiffel.pixelisation;

import java.awt.image.BufferedImage;

/** A very simple resolution changer using the value of the closest pixel (without taking into account the neighborhood) */
public class BasicResolutionChanger implements PixelizationMethod {
    @Override
    public void pixelize(BufferedImage source, BufferedImage destination) {
        double widthRatio = (double)destination.getWidth() / source.getWidth();
        double heightRatio = (double)destination.getHeight() / source.getHeight();
        for (int x = 0; x < destination.getWidth(); x++)
            for (int y = 0; y < destination.getHeight(); y++)
                destination.setRGB(x, y, source.getRGB((int)(x / widthRatio),  (int)(y / heightRatio)));
    }

}