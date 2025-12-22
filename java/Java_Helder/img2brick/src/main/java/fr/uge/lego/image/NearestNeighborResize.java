package fr.uge.lego.image;

import java.awt.image.BufferedImage;
import java.util.Objects;

import fr.uge.lego.matrix.Matrix;

public class NearestNeighborResize implements ResizeStrategy {

	@Override
	public BufferedImage resize(BufferedImage input, int newWidth, int newHeight) {
		Objects.requireNonNull(input);
		Matrix matrix = new Matrix(input.getHeight(), input.getWidth());
		matrix.nearestNeighborInterpolation(input, newHeight, newWidth);
		return matrix.setMatrixToImage();
	}
}
