<?php 
namespace Mita\UranusHttpServer\Middlewares;

use Mita\UranusHttpServer\Services\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;

class LocaleMiddleware implements MiddlewareInterface
{
    protected TranslatorInterface $translator;
    protected CacheInterface $cache;

    public function __construct(TranslatorInterface $translator, CacheInterface $cache)
    {
        $this->translator = $translator;
        $this->cache = $cache;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $locale = $request->getHeaderLine('Accept-Language') ?: 'en';
        $locale = explode(',', $locale)[0];
        $this->translator->setLocale($locale);

        return $handler->handle($request);
    }
}
