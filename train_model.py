import pandas as pd
import joblib

from sklearn.model_selection import train_test_split
from sklearn.compose import ColumnTransformer
from sklearn.preprocessing import OneHotEncoder
from sklearn.pipeline import Pipeline
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, classification_report


print("==============================================")
print("       PET ADOPTION AI TRAINING SYSTEM")
print("==============================================")



data = pd.read_csv("training_data.csv")

print("\nTraining dataset loaded.")
print("Total records:", len(data))



features = [

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

X = data[features]

y = data["result"]



print("\nFeatures:")

for feature in features:
    print(" -", feature)

print("\nClass distribution:")
print(y.value_counts())



preprocessor = ColumnTransformer(

    transformers=[

        (
            "categorical",

            OneHotEncoder(
                handle_unknown="ignore"
            ),

            features
        )

    ]

)



classifier = RandomForestClassifier(

    n_estimators=300,

    max_depth=None,

    min_samples_split=2,

    min_samples_leaf=1,

    class_weight="balanced",

    random_state=42,

    n_jobs=-1

)



model = Pipeline(

    steps=[

        (
            "preprocessor",
            preprocessor
        ),

        (
            "classifier",
            classifier
        )

    ]

)



X_train, X_test, y_train, y_test = train_test_split(

    X,

    y,

    test_size=0.20,

    random_state=42,

    stratify=y

)


print("\nTraining records:", len(X_train))

print("Testing records:", len(X_test))



print("\nTraining AI model...")

model.fit(
    X_train,
    y_train
)

print("Training completed!")



predictions = model.predict(
    X_test
)

accuracy = accuracy_score(
    y_test,
    predictions
)


print("\n==============================================")
print("             MODEL EVALUATION")
print("==============================================")

print(
    "Accuracy:",
    round(
        accuracy * 100,
        2
    ),
    "%"
)


print("\nClassification Report:")

print(

    classification_report(

        y_test,

        predictions,

        zero_division=0

    )

)



model_file = "pet_compatibility_model.pkl"


joblib.dump(

    model,

    model_file

)


print("\n==============================================")
print("             MODEL SAVED")
print("==============================================")

print(
    "Saved as:",
    model_file
)

print("\nAI training completed successfully.")

print("\nThis model can now be used by predict_api.py.")