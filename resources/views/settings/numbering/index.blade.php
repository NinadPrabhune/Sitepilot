@extends('layouts.main')

@section('content')
<div class="container-fluid">
    <div class="page-title">
        <div class="row">
            <div class="col-6">
                <h3>{{ __('Numbering Configuration') }}</h3>
            </div>
            <div class="col-6 text-end">
                <a href="{{ route('settings.numbering.audit') }}" class="btn btn-info">
                    <i class="ti ti-history"></i> {{ __('Audit Log') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Current Configurations') }}</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>{{ __('Module') }}</th>
                                <th>{{ __('Scope Type') }}</th>
                                <th>{{ __('Scope ID') }}</th>
                                <th>{{ __('Prefix') }}</th>
                                <th>{{ __('Starting Number') }}</th>
                                <th>{{ __('Padding Length') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $configs = \DB::table('numbering_configs')->orderBy('module')->orderBy('scope_type')->get();
                            @endphp
                            @if($configs->count() > 0)
                                @foreach($configs as $config)
                                <tr>
                                    <td>{{ strtoupper($config->module) }}</td>
                                    <td>{{ $config->scope_type }}</td>
                                    <td>{{ $config->scope_id ?? 'Global' }}</td>
                                    <td>{{ $config->prefix }}</td>
                                    <td>{{ $config->starting_number }}</td>
                                    <td>{{ $config->padding_length }}</td>
                                </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="6" class="text-center">{{ __('No configurations found. Run the seeder to create default configurations.') }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Add New Configuration') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('settings.numbering.update') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ __('Module') }}</label>
                                    <select name="module" class="form-control" required>
                                        <option value="po">{{ __('Purchase Order') }}</option>
                                        <option value="indent">{{ __('Indent') }}</option>
                                        <option value="grn">{{ __('GRN') }}</option>
                                        <option value="invoice">{{ __('Invoice') }}</option>
                                        <option value="payment">{{ __('Payment') }}</option>
                                        <option value="machinery_payment">{{ __('Machinery Payment') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ __('Scope Type') }}</label>
                                    <select name="scope_type" class="form-control" required>
                                        <option value="workspace">{{ __('Workspace') }}</option>
                                        <option value="site">{{ __('Site') }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ __('Scope ID') }}</label>
                                    <select name="scope_id" class="form-control">
                                        <option value="">{{ __('Global') }}</option>
                                        @foreach($workspaces as $id => $name)
                                        <option value="{{ $id }}">{{ $name }} (Workspace)</option>
                                        @endforeach
                                        @foreach($sites as $id => $name)
                                        <option value="{{ $id }}">{{ $name }} (Site)</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ __('Prefix') }}</label>
                                    <input type="text" name="prefix" class="form-control" maxlength="20" required placeholder="e.g., PO">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ __('Starting Number') }}</label>
                                    <input type="number" name="starting_number" class="form-control" min="1" value="1" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>{{ __('Padding Length') }}</label>
                                    <input type="number" name="padding_length" class="form-control" min="1" max="10" value="5" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>&nbsp;</label>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ti ti-device-floppy"></i> {{ __('Save Configuration') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Configuration Guide') }}</h5>
                </div>
                <div class="card-body">
                    <h6>{{ __('Company-Wise Prefix Setup') }}</h6>
                    <p>To set up different prefixes for different companies (workspaces):</p>
                    <ul>
                        <li><strong>Scope Type:</strong> Select <em>Workspace</em></li>
                        <li><strong>Scope ID:</strong> Select the specific company/workspace</li>
                        <li><strong>Prefix:</strong> Enter a company-specific prefix (e.g., <code>COMP1-PO</code>, <code>ACME-IND</code>)</li>
                    </ul>
                    <p><strong>Example:</strong> For Company A, use prefix <code>COMP1-PO</code> to generate numbers like <code>COMP1-PO00001</code></p>
                    
                    <div class="alert alert-info mt-2">
                        <strong>ℹ️ Hierarchical Lookup:</strong> If you have both a global configuration (Scope ID: Global) and a company-specific configuration (Scope ID: specific company), the system will use the company-specific configuration for that company and fall back to the global configuration for other companies.
                        <br><br>
                        <strong>Example:</strong>
                        <ul class="mb-0">
                            <li>Global: PO → Generates <code>PO00001</code> for all companies</li>
                            <li>Company A (ID: 1): COMP1-PO → Generates <code>COMP1-PO00001</code> for Company A only</li>
                            <li>Company B (ID: 2): No specific config → Falls back to global <code>PO00001</code></li>
                        </ul>
                    </div>
                    
                    <hr>
                    
                    <h6>{{ __('Site-Wise Prefix Setup') }}</h6>
                    <p>To set up different prefixes for different sites within a company:</p>
                    <ul>
                        <li><strong>Scope Type:</strong> Select <em>Site</em></li>
                        <li><strong>Scope ID:</strong> Select the specific site/project</li>
                        <li><strong>Prefix:</strong> Enter a site-specific prefix (e.g., <code>SITE1-GRN</code>, <code>PROJECT2-INV</code>)</li>
                    </ul>
                    <p><strong>Example:</strong> For Site 1, use prefix <code>SITE1-GRN</code> to generate numbers like <code>SITE1-GRN00001</code></p>
                    
                    <hr>
                    
                    <h6>{{ __('Module-Specific Scope Rules') }}</h6>
                    <ul>
                        <li><strong>Purchase Order (PO):</strong> Must use <em>Workspace</em> scope (company-level numbering)</li>
                        <li><strong>Indent, GRN, Invoice, Payment, Machinery Payment:</strong> Can use <em>Site</em> scope (site-level numbering)</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>{{ __('Best Practices') }}</h6>
                    <ul>
                        <li>Use descriptive prefixes that identify the company/site (e.g., <code>NYC-PO</code>, <code>LONDON-GRN</code>)</li>
                        <li>Keep prefixes under 20 characters</li>
                        <li>Use padding length of 5 or more for better readability (e.g., <code>00001</code> instead of <code>1</code>)</li>
                        <li>Avoid changing prefixes once documents have been generated to prevent confusion</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
