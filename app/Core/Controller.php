<?php
declare(strict_types=1);

namespace App\Core;

/** Base class for controllers. Controllers stay thin: read input, call a service, return a Response. */
abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], string $layout = 'layouts/app'): Response
    {
        $GLOBALS['current_path'] = $this->request->path;

        return Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $path): Response
    {
        return Response::redirect($path);
    }

    /** Redirect with a one-time banner message. $type: success | error | warning | info */
    protected function redirectWith(string $path, string $type, string $message): Response
    {
        Session::flash($type, $message);

        return Response::redirect($path);
    }

    /**
     * Sends the user back to a form with their input and the validation messages.
     * Password fields are never flashed back.
     *
     * @param array<string,string> $errors
     */
    protected function backWithErrors(string $path, array $errors, ?array $old = null): Response
    {
        $old ??= $this->request->body();
        foreach (array_keys($old) as $key) {
            if (stripos((string) $key, 'password') !== false || $key === Csrf::FIELD) {
                unset($old[$key]);
            }
        }
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        Session::flash('error', 'Please fix the highlighted fields and try again.');

        return Response::redirect($path);
    }

    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function forbidden(): never
    {
        throw new HttpException(403);
    }

    protected function notFound(): never
    {
        throw new HttpException(404);
    }
}
