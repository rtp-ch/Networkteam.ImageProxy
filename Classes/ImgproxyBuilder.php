<?php

namespace Networkteam\ImageProxy;

use Neos\Media\Domain\Model\ThumbnailConfiguration;
use Networkteam\ImageProxy\Model\Dimensions;

class ImgproxyBuilder implements ImgproxyBuilderInterface
{

    /**
     * Resizes the image while keeping aspect ratio to fit a given size.
     */
    public const RESIZE_TYPE_FIT = 'fit';
    /**
     * Resizes the image while keeping aspect ratio to fill a given size and crops projecting parts.
     */
    public const RESIZE_TYPE_FILL = 'fill';
    /**
     * Resizes the image without keeping the aspect ratio.
     */
    public const RESIZE_TYPE_FORCE = 'force';

    protected string $imgproxyUrl;
    protected ThumbnailConfiguration $thumbnailConfiguration;
    protected ?string $key = null;
    protected ?string $salt = null;

    /**
     * @param string $imgproxyUrl The URL where imgproxy is publicly available
     * @param ThumbnailConfiguration $thumbnailConfiguration
     * @param string|null $key
     * @param string|null $salt
     */
    public function __construct(
        string $imgproxyUrl,
        ThumbnailConfiguration $thumbnailConfiguration,
        string $key = null,
        string $salt = null
    ) {
        if ((string)$key !== '' && (string)$salt !== '') {
            $this->key = pack("H*", $key);
            $this->salt = pack("H*", $salt);
        }

        $this->imgproxyUrl = $imgproxyUrl;
        $this->thumbnailConfiguration = $thumbnailConfiguration;
    }

    /**
     * Calculate the expected size of the resulting image after it is processed by imgproxy
     *
     * @param Dimensions $actualDimension
     * @param Dimensions $targetDimension
     * @param string $resizingType
     * @param bool $enlarge
     * @return Dimensions
     * @internal
     */
    public static function expectedSize(Dimensions $actualDimension, Dimensions $targetDimension, string $resizingType, bool $enlarge): Dimensions
    {
        if ($actualDimension->noWidth() || $actualDimension->noHeight()) {
            return $targetDimension;
        }

        if ($targetDimension->isZero()) {
            return $actualDimension;
        }

        // Use the actual aspect ratio as the default target aspect ratio
        $targetAspectRatio = $actualDimension->getAspectRatio();

        if (!$targetDimension->noHeight() && !$targetDimension->noWidth()) {
            $targetAspectRatio = $targetDimension->getAspectRatio();
        }

        if (!$enlarge && ($targetDimension->contains($actualDimension))) {
            return $actualDimension;
        }

        if ($resizingType === ImgproxyBuilder::RESIZE_TYPE_FORCE || $resizingType === ImgproxyBuilder::RESIZE_TYPE_FILL) {
            return $targetDimension;
        }

        // The actual image is wider than the expected target image or target height is not known -> restrict by width
        if ($targetDimension->getHeight() === 0 || $actualDimension->getAspectRatio() > $targetAspectRatio) {
            return new Dimensions($targetDimension->getWidth(), $targetDimension->getWidth() / $actualDimension->getAspectRatio());
        }

        // The actual image is narrower than the expected target image (or equal, but doesn't matter) or target width is not known -> restrict by height
        return new Dimensions($actualDimension->getAspectRatio() * $targetDimension->getHeight(), $targetDimension->getHeight());
    }

    /**
     * Build an imgproxy URL by starting with a source URL to the image. Additional processing options can be set by
     * using the returned builder instance. With `$url->build()` the final URL can be generated.
     *
     * Depending on the imgproxy configuration, that could be a http, local or s3 URL.
     *
     * @param string $sourceUrl
     * @return ImgproxyUrl
     */
    public function buildUrl(string $sourceUrl): ImgproxyUrl
    {
        return new ImgproxyUrl($this, $sourceUrl);
    }

    /**
     * @param ImgproxyUrl $imgproxyUrl
     * @return string
     * @internal
     * Generate an imgproxy URL with processing options and signature (if key and salt are set).
     *
     */
    public function generateUrl(ImgproxyUrl $imgproxyUrl): string
    {
        $encodedSourceUrl = self::base64UrlEncode($imgproxyUrl->getUrl());
        $pathAfterSignature = '/' . implode('/', $imgproxyUrl->getProcessingOptions()) . '/' . $encodedSourceUrl;
        if ($imgproxyUrl->getExtension() !== null) {
            $pathAfterSignature .= '.' . $imgproxyUrl->getExtension();
        }

        if ($this->key !== null) {
            $data = $this->salt . $pathAfterSignature;
            $signature = hash_hmac('sha256', $data, $this->key, true);

            return $this->imgproxyUrl . '/' . self::base64UrlEncode($signature) . $pathAfterSignature;
        } else {
            return $this->imgproxyUrl . '/insecure' . $pathAfterSignature;
        }
    }

    protected static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
