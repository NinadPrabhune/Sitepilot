<script src="https://js.pusher.com/7.0.3/pusher.min.js"></script>
<script >
    // Enable pusher logging - don't include this in production
    Pusher.logToConsole = true;
//    Pusher.logToConsole = true
    var pusher = new Pusher("{{ config('chatify.pusher.key') }}", {
    encrypted: true,
            cluster: "{{ config('chatify.pusher.options.cluster') }}",
            authEndpoint: '{{route("pusher.auth")}}',
            auth: {
            headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
            }
    });



// Subscribe to the private channel for the current user



//    var channel = pusher.subscribe('private-notifications.{{ auth()->id() }}');
//    channel.bind('notification.new', function(data) {
//    alert("New Notification: " + data.body); // example
//    });


//    var channel = pusher.subscribe('private-chatify');
//    channel.bind('messaging', function(data) {
//
//
//
//
//
//    });

</script>
<!--<script>
    // Subscribe to private-chatify
    var channel = pusher.subscribe('private-chatify');

    channel.bind('messaging', function(data) {

        // 1. Increment unseen counter
        let counterEl = document.querySelector('.custom_messanger_counter');
        if (counterEl) {
            let currentCount = parseInt(counterEl.textContent) || 0;
            counterEl.textContent = currentCount + 1;
        }

        // 2. Play iPhone message sound
        var audio = new Audio('/sounds/iphone_message_tone.mp3');
        audio.play();
    });
</script>-->



<script src="{{ asset('js/chatify/code.js') }}"></script>
<script>
    // Messenger global variable - 0 by default
    messenger = "{{ @$id }}";
</script>
