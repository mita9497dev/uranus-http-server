<?php 
namespace Mita\UranusHttpServer\Services;

use Symfony\Component\Translation\Loader\JsonFileLoader;
use Symfony\Component\Translation\Translator;

class TranslatorService implements TranslatorInterface
{
    protected Translator $translator;

    public function __construct(string $locale, string $translationDir)
    {
        $this->translator = new Translator($locale);
        $this->translator->addLoader('json', new JsonFileLoader());

        foreach (glob($translationDir . '/*.json') as $file) {
            $filename = basename($file, '.json');
            $this->translator->addResource('json', $file, $filename);
        }
    }

    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }

    public function setLocale(string $locale)
    {
        $this->translator->setLocale($locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
