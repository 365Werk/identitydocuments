<?php


namespace werk365\IdentityDocuments\Filters;


use Intervention\Image\Filters\FilterInterface;
use Intervention\Image\Image;
use Intervention\Image\Facades\Image as Img;

class MergeFilter implements FilterInterface
{
    /**
     * Size of filter effects
     *
     * @var Intervention\Image\Image
     */
    private $add_img;

    /**
     * Creates new instance of filter
     *
     * @param Intervention\Image\Image $image
     */
    public function __construct(Image $image)
    {
        $this->add_img = $image;
    }

    /**
     * Applies filter effects to given image
     *
     * @param Intervention\Image\Image $image
     * @return \Intervention\Image\Image
     */
    public function applyFilter(Image $image)
    {
        $base_img_x = $image->width();
        $base_img_y = $image->height();
        $add_img_x = $this->add_img->width();
        $add_img_y = $this->add_img->height();
        $canvas_x = $base_img_x + $add_img_x;
        $canvas_y = ($base_img_y > $add_img_y) ? $base_img_y : $add_img_y;
        $canvas = Img::canvas($canvas_x, $canvas_y, "#ffffff");
        $canvas->insert($image, 'top-left');
        $canvas->insert($this->add_img, 'top-left', $base_img_x);

        return $canvas;
    }
}
