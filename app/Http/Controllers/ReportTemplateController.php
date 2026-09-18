<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use Illuminate\Http\Request;

class ReportTemplateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $templates = ReportTemplate::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return view('reports.templates.index', [
            'activeTab' => 'report.templates',
            'templates' => $templates,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     * We don't necessarily need a create view since templates are created from the builder.
     */
    public function create()
    {
        return redirect()->route('report.builder');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'           => 'required|string|max:255',
            'connection_key' => 'required|string',
            'table_name'     => 'required|string',
            'configuration'  => 'required|array',
        ]);

        $template = ReportTemplate::create([
            'user_id'        => auth()->id(),
            'name'           => $request->input('name'),
            'connection_key' => $request->input('connection_key'),
            'table_name'     => $request->input('table_name'),
            'configuration'  => $request->input('configuration'),
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'template' => $template, 'message' => 'Report template saved successfully.']);
        }

        return redirect()->route('report-templates.index')->with('success', 'Report template saved successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ReportTemplate $reportTemplate)
    {
        // View can just redirect to edit
        return redirect()->route('report-templates.edit', $reportTemplate->id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        $template = ReportTemplate::where('user_id', auth()->id())->findOrFail($id);

        return view('reports.templates.edit', [
            'activeTab' => 'report.templates',
            'template'  => $template,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $template = ReportTemplate::where('user_id', auth()->id())->findOrFail($id);

        $request->validate([
            'name'           => 'required|string|max:255',
            'connection_key' => 'sometimes|required|string',
            'table_name'     => 'sometimes|required|string',
            'configuration'  => 'sometimes|required|array',
        ]);

        $data = ['name' => $request->input('name')];
        if ($request->has('connection_key')) $data['connection_key'] = $request->input('connection_key');
        if ($request->has('table_name')) $data['table_name'] = $request->input('table_name');
        if ($request->has('configuration')) $data['configuration'] = $request->input('configuration');

        $template->update($data);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'template' => $template, 'message' => 'Report template updated successfully.']);
        }

        return redirect()->route('report-templates.index')->with('success', 'Template updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $template = ReportTemplate::where('user_id', auth()->id())->findOrFail($id);
        $template->delete();

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Report template deleted successfully.']);
        }

        return redirect()->route('report-templates.index')->with('success', 'Report template deleted successfully.');
    }
}
