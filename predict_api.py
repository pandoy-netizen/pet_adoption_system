from flask import Flask, request, jsonify
from flask_cors import CORS

import joblib
import pandas as pd




app = Flask(__name__)

CORS(app)




MODEL_FILE = "pet_compatibility_model.pkl"

model = None


try:

    model = joblib.load(
        MODEL_FILE
    )

    print("==============================================")
    print("          PET ADOPTION AI API")
    print("==============================================")

    print(
        "Trained model loaded successfully."
    )

except Exception as error:

    print("==============================================")
    print("          MODEL LOAD ERROR")
    print("==============================================")

    print(error)




FEATURES = [

    "adopter_living_space",
    "adopter_children",
    "adopter_experience",
    "adopter_activity",
    "adopter_temperament",

    "pet_activity",
    "pet_temperament",
    "pet_species",
    "pet_size"

]



def normalize(value):

    if value is None:

        return ""

    return str(
        value
    ).strip().lower()



def normalize_species(value):

    value = normalize(value)

    if value in [

        "dog",
        "dogs",
        "puppy",
        "puppies",
        "canine"

    ]:

        return "Dog"


    if value in [

        "cat",
        "cats",
        "kitten",
        "kittens",
        "feline"

    ]:

        return "Cat"


    return value.title()


def normalize_activity(value):

    value = normalize(value)


    if value in [
        "low",
        "low energy",
        "quiet"
    ]:

        return "Low"


    if value in [
        "medium",
        "moderate",
        "moderately active"
    ]:

        return "Medium"


    if value in [
        "high",
        "very active",
        "energetic"
    ]:

        return "High"


    return "Medium"



def normalize_temperament(value):

    value = normalize(value)


    if "calm" in value:

        return "Calm"


    if "friendly" in value:

        return "Friendly"


    if "active" in value:

        return "Active"


    if "aggressive" in value:

        return "Aggressive"


    return "Friendly"



@app.route(
    "/",
    methods=["GET"]
)

def home():

    return jsonify({

        "status": "online",

        "message":
            "Pet Adoption AI is running.",

        "model":
            MODEL_FILE,

        "model_loaded":
            model is not None

    })



@app.route(
    "/predict",
    methods=["POST"]
)

def predict():

    try:

        if model is None:

            return jsonify({

                "success": False,

                "error":
                    "AI model was not loaded."

            }), 500


        data = request.get_json()


        if not data:

            return jsonify({

                "success": False,

                "error":
                    "No JSON data received."

            }), 400


        record = {

            "adopter_living_space":
                data.get(
                    "adopter_living_space",
                    "House"
                ),

            "adopter_children":
                data.get(
                    "adopter_children",
                    "No"
                ),

            "adopter_experience":
                data.get(
                    "adopter_experience",
                    "Beginner"
                ),

            "adopter_activity":
                normalize_activity(
                    data.get(
                        "adopter_activity",
                        "Medium"
                    )
                ),

            "adopter_temperament":
                normalize_temperament(
                    data.get(
                        "adopter_temperament",
                        "Friendly"
                    )
                ),

            "pet_activity":
                normalize_activity(
                    data.get(
                        "pet_activity",
                        "Medium"
                    )
                ),

            "pet_temperament":
                normalize_temperament(
                    data.get(
                        "pet_temperament",
                        "Friendly"
                    )
                ),

            "pet_species":
                normalize_species(
                    data.get(
                        "pet_species",
                        ""
                    )
                ),

            "pet_size":
                data.get(
                    "pet_size",
                    "Medium"
                )

        }



        input_data = pd.DataFrame(
            [record],
            columns=FEATURES
        )



        prediction = model.predict(
            input_data
        )[0]


        probabilities = model.predict_proba(
            input_data
        )[0]


        classes = model.classes_


        probability_map = {

            str(class_name):
                round(
                    float(
                        probabilities[index]
                    ) * 100,

                    2
                )

            for index, class_name
            in enumerate(classes)

        }


        compatible_score = probability_map.get(

            "Compatible",

            0

        )


        return jsonify({

            "success": True,

            "prediction":
                prediction,

            "compatibility_score":
                round(
                    compatible_score,
                    2
                ),

            "probabilities":
                probability_map

        })


    except Exception as error:

        print("\nAI ERROR:")

        print(error)


        return jsonify({

            "success": False,

            "error":
                str(error)

        }), 500



@app.route(
    "/match",
    methods=["POST"]
)

def match():

    try:

        if model is None:

            return jsonify({

                "success": False,

                "error":
                    "AI model was not loaded."

            }), 500


        data = request.get_json()


        if not data:

            return jsonify({

                "success": False,

                "error":
                    "No JSON data received."

            }), 400


        adopter = data.get(
            "adopter",
            {}
        )


        pets = data.get(
            "pets",
            []
        )


        target_species = normalize_species(

            adopter.get(
                "target_species",
                ""
            )

        )



        records = []

        valid_pets = []


        for pet in pets:

            pet_species = normalize_species(

                pet.get(
                    "species",
                    ""
                )

            )


            if (

                target_species in [
                    "Dog",
                    "Cat"
                ]

                and pet_species != target_species

            ):

                continue


            record = {

                "adopter_living_space":
                    adopter.get(
                        "living_space",
                        "House"
                    ),

                "adopter_children":
                    adopter.get(
                        "children",
                        "No"
                    ),

                "adopter_experience":
                    adopter.get(
                        "experience",
                        "Beginner"
                    ),

                "adopter_activity":
                    normalize_activity(
                        adopter.get(
                            "activity",
                            "Medium"
                        )
                    ),

                "adopter_temperament":
                    normalize_temperament(
                        adopter.get(
                            "temperament",
                            "Friendly"
                        )
                    ),

                "pet_activity":
                    normalize_activity(
                        pet.get(
                            "activity_level",
                            "Medium"
                        )
                    ),

                "pet_temperament":
                    normalize_temperament(
                        pet.get(
                            "temperament",
                            "Friendly"
                        )
                    ),

                "pet_species":
                    pet_species,

                "pet_size":
                    pet.get(
                        "size",
                        "Medium"
                    )

            }


            records.append(
                record
            )

            valid_pets.append(
                pet
            )



        if len(records) == 0:

            return jsonify({

                "success": True,

                "matches": [],

                "message":
                    "No pets matched the requested species."

            })



        input_data = pd.DataFrame(

            records,

            columns=FEATURES

        )


        predictions = model.predict(
            input_data
        )


        probabilities = model.predict_proba(
            input_data
        )


        classes = list(
            model.classes_
        )



        if "Compatible" in classes:

            compatible_index = classes.index(
                "Compatible"
            )

        else:

            compatible_index = None


        results = []



        for index, pet in enumerate(valid_pets):

            if compatible_index is not None:

                ai_score = (

                    float(
                        probabilities[
                            index
                        ][
                            compatible_index
                        ]
                    ) * 100

                )

            else:

                ai_score = 0


            ai_score = round(
                ai_score,
                2
            )



            reasons = []


            adopter_activity = normalize_activity(

                adopter.get(
                    "activity",
                    "Medium"
                )

            )


            pet_activity = normalize_activity(

                pet.get(
                    "activity_level",
                    "Medium"
                )

            )


            adopter_temp = normalize_temperament(

                adopter.get(
                    "temperament",
                    "Friendly"
                )

            )


            pet_temp = normalize_temperament(

                pet.get(
                    "temperament",
                    "Friendly"
                )

            )


            if (

                adopter_activity ==
                pet_activity

            ):

                reasons.append(
                    "Activity level matches your lifestyle"
                )


            if (

                adopter_temp ==
                pet_temp

            ):

                reasons.append(
                    "Temperament matches your preference"
                )


            if (

                target_species ==

                normalize_species(
                    pet.get(
                        "species",
                        ""
                    )
                )

            ):

                reasons.append(
                    "Correct species preference"
                )


            if not reasons:

                reasons.append(
                    "AI analyzed the pet's characteristics"
                )



            if ai_score >= 85:

                label = "Highly Recommended"

            elif ai_score >= 70:

                label = "Strong Match"

            elif ai_score >= 55:

                label = "Good Match"

            else:

                label = "Possible Match"


            results.append({

                "id":
                    pet.get(
                        "id"
                    ),

                "name":
                    pet.get(
                        "name",
                        "Pet"
                    ),

                "breed":
                    pet.get(
                        "breed",
                        "Mixed Breed"
                    ),

                "species":
                    pet.get(
                        "species",
                        ""
                    ),

                "size":
                    pet.get(
                        "size",
                        "Medium"
                    ),

                "temperament":
                    pet.get(
                        "temperament",
                        "Friendly"
                    ),

                "activity_level":
                    pet.get(
                        "activity_level",
                        "Medium"
                    ),

                "image":
                    pet.get(
                        "display_image",
                        "uploads/default.jpg"
                    ),

                "match_score":
                    ai_score,

                "match_label":
                    label,

                "prediction":
                    str(
                        predictions[index]
                    ),

                "match_reasons":
                    reasons[:3]

            })



        results.sort(

            key=lambda item:
                item["match_score"],

            reverse=True

        )



        return jsonify({

            "success": True,

            "target_species":
                target_species,

            "total_matches":
                len(results),

            "matches":
                results

        })


    except Exception as error:

        print("\n==============================================")
        print("MATCH API ERROR")
        print("==============================================")

        print(error)


        return jsonify({

            "success": False,

            "error":
                str(error)

        }), 500



if __name__ == "__main__":

    print("\n==============================================")
    print("       PET ADOPTION AI API SERVER")
    print("==============================================")

    print(
        "Running on http://127.0.0.1:5000"
    )

    app.run(

        host="127.0.0.1",

        port=5000,

        debug=True

    )