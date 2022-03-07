<?php

namespace Networkteam\ImageProxy\Tests\Unit;

use Networkteam\ImageProxy\ImgproxyBuilder;

class ImgproxyBuilderTest extends \Neos\Flow\Tests\UnitTestCase
{

    /**
     * @test
     */
    public function generateLocalUrlWithoutSignature()
    {
        $builder = new ImgproxyBuilder("http://localhost:8084");
        $url = $builder->buildUrl("local:///path/to/image.jpg")
            ->resize(ImgproxyBuilder::RESIZE_TYPE_FIT, 300, 200, false, true)
            ->extension('png')
            ->build();

        $this->assertEquals('http://localhost:8084/insecure/rs:fit:300:200:0:1/bG9jYWw6Ly8vcGF0aC90by9pbWFnZS5qcGc.png', $url);
    }

    /**
     * @test
     */
    public function generateLocalUrlWithSignature()
    {
        $builder = new ImgproxyBuilder("http://localhost:8084", '736563726574', '68656C6C6F');
        $url = $builder->buildUrl("local:///path/to/image.jpg")
            ->resize(ImgproxyBuilder::RESIZE_TYPE_FILL, 300, 400, false, false)
            ->extension('png')
            ->build();

        $this->assertEquals('http://localhost:8084/4EjfKMTf6eZ9q6_n5l3Woc3AsbRfsXJ6lgNbqe2mOvY/rs:fill:300:400:0:0/bG9jYWw6Ly8vcGF0aC90by9pbWFnZS5qcGc.png', $url);
    }

    /**
     * @test
     * @dataProvider expectedSizeExamples
     */
    public function expectedSize(?int $actualWidth, ?int $actualHeight, int $targetWidth, int $targetHeight, string $resizingType, bool $enlarge, int $expectedWidth, int $expectedHeight)
    {
        $actualExpectedSize = ImgproxyBuilder::expectedSize($actualWidth, $actualHeight, $targetWidth, $targetHeight, $resizingType, $enlarge);

        $this->assertEquals($expectedWidth, $actualExpectedSize['width']);
        $this->assertEquals($expectedHeight, $actualExpectedSize['height']);
    }

    public function expectedSizeExamples(): array
    {
        return [
            [
                1000, 800,
                400, 300,
                ImgproxyBuilder::RESIZE_TYPE_FIT,
                false,
                375, 300,
            ],
            [
                1000, 500,
                400, 300,
                ImgproxyBuilder::RESIZE_TYPE_FIT,
                false,
                400, 200,
            ],
            [
                1000, 800,
                400, 300,
                ImgproxyBuilder::RESIZE_TYPE_FILL,
                false,
                400, 300,
            ],
            [
                1000, 500,
                400, 300,
                ImgproxyBuilder::RESIZE_TYPE_FILL,
                false,
                400, 300,
            ],
            [
                800, 600,
                200, 300,
                ImgproxyBuilder::RESIZE_TYPE_FORCE,
                false,
                200, 300,
            ],
        ];
    }
}