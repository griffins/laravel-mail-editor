<?php

namespace qoraiche\mailEclipse\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use qoraiche\mailEclipse\mailEclipse;
use Illuminate\Support\Facades\View;

class MailablesController extends Controller
{

    public function __construct()
    {
        abort_unless(
            App::environment(config('maileclipse.allowed_environments', ['local'])),
            403
        );
    }

    public function toMailablesList()
    {
        return redirect()->route('mailableList');
    }


    public function index()
    {

        $mailables = mailEclipse::getMailables();

        $mailables = (null !== $mailables) ? $mailables->sortBy('name') : collect([]);

        return view(mailEclipse::$view_namespace . '::sections.mailables', compact('mailables'));

    }

    public function createMailable(Request $request)
    {

        return view(mailEclipse::$view_namespace . '::createmailable');
    }

    public function generateMailable(Request $request)
    {

        return mailEclipse::generateMailable($request);
    }

    public function viewMailable($name)
    {

        $mailable = mailEclipse::getMailable('name', $name);

        if ($mailable->isEmpty()) {
            return redirect()->route('mailableList');
        }

        $resource = $mailable->first();

        return view(mailEclipse::$view_namespace . '::sections.view-mailable')->with(compact('resource'));
    }

    public function editMailable($name)
    {

        $templateData = mailEclipse::getMailableTemplateData($name);

        if (!$templateData) {

            return redirect()->route('viewMailable', ['name' => $name]);
        }

        return view(mailEclipse::$view_namespace . '::sections.edit-mailable-template', compact('templateData', 'name'));
    }


    public function templatePreviewError()
    {

        return view(mailEclipse::$view_namespace . '::previewerror');
    }

    public function parseTemplate(Request $request)
    {

        $template = $request->has('template') ? $request->template : false;

        $viewPath = $request->has('template') ? $request->viewpath : base64_decode($request->viewpath);
        if (mailEclipse::markdownedTemplateToView(true, $request->markdown, $viewPath, $template)) {

            return response()->json([
                'status' => 'ok',
            ]);
        }

        return response()->json([
            'status' => 'error',
        ]);

    }

    public function previewMarkdownView(Request $request)
    {
        return mailEclipse::previewMarkdownViewContent(false, $request->markdown, $request->name, false, $request->namespace);
    }

    public function previewMailable($name)
    {

        $mailable = mailEclipse::getMailable('name', $name);

        if ($mailable->isEmpty()) {
            return redirect()->route('mailableList');
        }

        $resource = $mailable->first();

        if (!is_null(mailEclipse::handleMailableViewDataArgs($resource['namespace']))) {
            // $instance = new $resource['namespace'];
            //
            $instance = mailEclipse::handleMailableViewDataArgs($resource['namespace']);

        } else {
            $instance = new $resource['namespace'];
        }


        if (collect($resource['data'])->isEmpty()) {
            return 'View not found';

        }

        $view = !is_null($resource['markdown']) ? $resource['markdown'] : $resource['data']->view;

        if (view()->exists($view)) {

            try {

                $html = $instance;

                return $html->render();

            } catch (\ErrorException $e) {

                return view(mailEclipse::$view_namespace . '::previewerror', ['errorMessage' => $e->getMessage()]);
            }

        }

        return view(mailEclipse::$view_namespace . '::previewerror', ['errorMessage' => 'No template associated with this mailable.']);
    }


    public function delete(Request $request)
    {

        $mailableFile = config('maileclipse.mail_dir') . $request->mailablename . '.php';

        if (file_exists($mailableFile)) {

            unlink($mailableFile);

            return response()->json([
                'status' => 'ok',
            ]);
        }

        return response()->json([
            'status' => 'error',
        ]);
    }

}
