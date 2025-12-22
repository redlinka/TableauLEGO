package fr.uge.lego.image;

import java.awt.image.BufferedImage;
import java.util.List;
import java.util.Objects;

public class MultiStepResizeStrategy implements ResizeStrategy {

	private List<ResizeStrategy> steps;

	public MultiStepResizeStrategy(List<ResizeStrategy> steps) {
		this.steps = Objects.requireNonNull(steps);
	}

	@Override
	public BufferedImage resize(BufferedImage input, int finalWidth, int finalHeight) {
		Objects.requireNonNull(input);

		BufferedImage currentImage = input;
		int newHeight = currentImage.getHeight();
		int newWidth = currentImage.getWidth();
		int i = 0;
		while (newHeight != finalHeight && newWidth != finalWidth) {
			if( i != steps.size()-1) {
				i++;
			}
			newHeight = newHeight / 2;
			newWidth = newWidth / 2;
			ResizeStrategy step = steps.get(i);
			if (newWidth < finalWidth || newHeight < finalHeight) {
				newWidth = finalWidth;
				newHeight = finalHeight;
			}
			
			currentImage = step.resize(input, newWidth, newHeight);
			
		}

		return currentImage;
	}
}
