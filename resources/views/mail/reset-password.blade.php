@extends('mail.layout')

@section('title', 'Reset your Chill password')

@section('preheader', 'Choose a new password and get back to your picks.')

@section('headline')
Pick a new password<br>and carry on watching.
@endsection

@section('lede')
Someone asked to reset the password for <span style="font-weight: 600;">{{ $email }}</span>.
Tap the button to choose a new one. Your lists and picks stay exactly where you left them.
@endsection

@section('action', 'Reset my password')

@section('disclaimer')
Didn&rsquo;t ask for this? Ignore this email and your password stays the same. Nobody can change it without this link.
@endsection
