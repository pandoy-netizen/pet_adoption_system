

        document
            .getElementById('modalName')
            .innerText = name;


        document
            .getElementById('modalBreed')
            .innerText = breed;


        document
            .getElementById('modalAge')
            .innerText = age;


        document
            .getElementById('modalDesc')
            .innerText = desc;


        document
            .getElementById('modalImg')
            .src = img;


        modal.style.display = 'flex';


        setTimeout(() => {

            modal.classList.add('show');

        }, 10);

    }


    function closeModal() {

        modal.classList.remove('show');


        setTimeout(() => {

            modal.style.display = 'none';

        }, 400);

    }


    /* =====================================================
       CLOSE MODAL WHEN CLICKING OUTSIDE
    ===================================================== */

    window.onclick = function(event) {

        if (event.target == modal) {

            closeModal();

        }

    };


    /* =====================================================
       AUTO HIDE ALERT
    ===================================================== */

    setTimeout(() => {

        const alertBox =
            document.querySelector('.alert');


        if (alertBox) {

            alertBox.style.animation =
                'fadeOutUp 0.6s cubic-bezier(0.68,-0.55,0.265,1.55) forwards';


            setTimeout(() => {

                alertBox.remove();

            }, 600);

        }

    }, 4500);


    /* =====================================================
       SERVICE WORKER
    ===================================================== */

    if ('serviceWorker' in navigator) {

        navigator
            .serviceWorker
            .register('/service-worker.js')

            .then(() => {

                console.log(
                    "Service Worker Registered"
                );

            })

            .catch(err => {

                console.log(
                    "Service Worker Failed:",
                    err
                );

            });

    }

</script>


</body>
