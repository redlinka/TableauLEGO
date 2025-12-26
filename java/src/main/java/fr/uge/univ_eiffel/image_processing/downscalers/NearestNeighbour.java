package fr.uge.univ_eiffel.image_processing.downscalers;

import java.awt.image.BufferedImage;

public class NearestNeighbour implements Downscaler {

    /**
     * here is a rescaling method that uses the pixel from the source that is the closest to the one in the destination
     * result can sometimes result to a noisy or grainy result
     * Input: source, destination, both bufferedImages
     * Output: void.
     */

    public void downscale(BufferedImage source, BufferedImage destination) {
        double widthRatio = (double) destination.getWidth() / source.getWidth();
        double heightRatio = (double) destination.getHeight() / source.getHeight();
        for (int x = 0; x < destination.getWidth(); x++) {
            for (int y = 0; y < destination.getHeight(); y++) {
                destination.setRGB(x, y, source.getRGB((int) (x / widthRatio), (int) (y / heightRatio)));
            }
        }
    }
}
