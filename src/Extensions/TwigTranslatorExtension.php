<?php 
namespace Mita\UranusHttpServer\Extensions;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Mita\UranusHttpServer\Services\TranslatorService;
use Slim\Views\Twig;

class TwigTranslatorExtension extends AbstractExtension implements TwigExtensionRegistrarInterface
{
    protected TranslatorService $translationService;

    public function __construct(TranslatorService $translationService)
    {
        $this->translationService = $translationService;
    }

    public function getFilters()
    {
        return [
            new TwigFilter('trans', [$this, 'translate']),
        ];
    }

    public function getFunctions()
    {
        return [
            new TwigFunction('trans', [$this, 'translate']),
        ];
    }

    public function translate(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        return $this->translationService->trans($id, $parameters, $domain, $locale);
    }

    public function register(Twig $twig): void
    {
        $twig->addExtension($this);
    }
}