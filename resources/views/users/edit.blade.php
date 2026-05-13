@php
    if(Auth::user()->type=='super admin')
    {
        $name = __('Customer');
    }
    else{

        $name =__('User');
    }
@endphp
    {{Form::model($user,array('route' => array('users.update', $user->id), 'method' => 'PUT','class'=>'needs-validation','novalidate')) }}
    <div class="modal-body">
        <div class="row">
            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('name',__('Name'),['class'=>'form-label']) }}<x-required></x-required>
                    {{Form::text('name',null,array('class'=>'form-control','placeholder'=>__('Enter '.($name).' Name'),'required'=>'required'))}}
                </div>
            </div>
            <div class="col-md-12">
                <div class="form-group">
                    {{Form::label('email',__('Email'),['class'=>'form-label'])}}<x-required></x-required>
                    {{Form::email('email',null,array('class'=>'form-control','placeholder'=>__('Enter '.($name).' Email'),'required'=>'required'))}}
                </div>
            </div>
            
            
            @if(Auth::user()->isAbleTo('user manage'))
<!--                <div class="col-md-12">
                    <div class="form-group">
                        {{ Form::label('roles', __('Roles'),['class'=>'form-label']) }}<x-required></x-required>
                        {{ Form::select('roles',$roles, null, ['class' => 'form-control','placeholder'=>'Select Role', 'id' => 'roles','required'=>'required']) }}
                        
                        
                        <div class=" text-xs mt-1">
                            <span class="text-xs text-secondary">{{ __('Unable to modify this user`s role. Please ensure that the correct role has been assigned to this user.') }}</span><br>
                            {{ __('Create role here. ') }}
                            <a href="{{ route('roles.index') }}"><b>{{ __('Create role') }}</b></a>
                        </div>
                        
                    </div>
                </div>
                <x-mobile ></x-mobile>-->
            @endif
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{__('Cancel')}}</button>
        {{Form::submit(__('Update'),array('class'=>'btn  btn-primary'))}}
    </div>
    {{Form::close()}}
