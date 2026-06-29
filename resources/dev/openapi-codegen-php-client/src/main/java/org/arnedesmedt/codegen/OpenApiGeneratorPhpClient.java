package org.teamblue.codegen;

import io.swagger.v3.oas.models.OpenAPI;
import io.swagger.v3.oas.models.Operation;
import io.swagger.v3.oas.models.Components;
import io.swagger.v3.oas.models.media.ArraySchema;
import io.swagger.v3.oas.models.media.Content;
import io.swagger.v3.oas.models.media.ComposedSchema;
import io.swagger.v3.oas.models.media.Schema;
import io.swagger.v3.oas.models.media.MediaType;
import io.swagger.v3.oas.models.media.ObjectSchema;
import io.swagger.v3.oas.models.parameters.Parameter;
import io.swagger.v3.oas.models.parameters.QueryParameter;
import io.swagger.v3.oas.models.parameters.RequestBody;
import io.swagger.v3.oas.models.Paths;
import io.swagger.v3.oas.models.PathItem;
import io.swagger.v3.oas.models.PathItem.HttpMethod;
import io.swagger.v3.oas.models.responses.ApiResponses;
import io.swagger.v3.oas.models.responses.ApiResponse;

import java.util.*;
import java.util.stream.Collectors;
import java.util.function.Function;
import java.io.File;

import org.apache.commons.lang3.StringUtils;
import org.openapitools.codegen.*;
import org.openapitools.codegen.model.ModelMap;
import org.openapitools.codegen.model.ModelsMap;
import org.openapitools.codegen.utils.ModelUtils;
import org.openapitools.codegen.languages.PhpClientCodegen;
import org.openapitools.codegen.templating.mustache.EscapeChar;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

import com.fasterxml.jackson.databind.node.ArrayNode;

public class OpenApiGeneratorPhpClient extends PhpClientCodegen implements CodegenConfig {
  public static final String GENERATOR_NAME         = "openapi-codegen-php-client";
  public static final String HELP_URL               = "helpUrl";
  public static final String COPYRIGHT              = "copyright";
  public static final String CLIENT_CLASS           = "clientClass";
  public static final String CLIENT_CLASS_QUALIFIER = "clientClassQualifier";

  public OpenApiGeneratorPhpClient() {
    super();

    cliOptions.add(new CliOption(HELP_URL, "Help URL"));
    cliOptions.add(new CliOption(COPYRIGHT, "Copyright"));
    cliOptions.add(new CliOption(CLIENT_CLASS, "Client file"));

    typeMapping.put("date", "\\TeamBlue\\\\ValueObjects\\\\Implementation\\\\String\\\\DateTimeValue");
    typeMapping.put("Date", "\\TeamBlue\\\\ValueObjects\\\\Implementation\\\\String\\\\DateTimeValue");
    typeMapping.put("DateTime", "\\TeamBlue\\\\ValueObjects\\\\Implementation\\\\String\\\\DateTimeValue");
    typeMapping.put("set", "array");
    typeMapping.put("object", "array");

    this.setTemplateDir(OpenApiGeneratorPhpClient.GENERATOR_NAME);
    this.embeddedTemplateDir = this.templateDir();

    this.apiDirName = "Endpoint";
    setApiPackage(getInvokerPackage() + "\\" + apiDirName);
  }

  @Override
  public void processOpts() {
    super.processOpts();

    String clientClass = (String) additionalProperties.getOrDefault(CLIENT_CLASS, "Client");
    additionalProperties.put(CLIENT_CLASS, clientClass);

    if (clientClass.startsWith("Abstract")) {
      additionalProperties.put(CLIENT_CLASS_QUALIFIER, "abstract");
    }

    this.resetTemplateFiles();

    supportingFiles.add(new SupportingFile("client.mustache", "", clientClass.concat(".php")));
    supportingFiles.add(new SupportingFile("clientInterface.mustache", "", clientClass.concat("Interface.php")));
    supportingFiles.add(new SupportingFile("readme.mustache", "", "README.md"));
    supportingFiles.add(new SupportingFile("phpstan.mustache", "", "phpstan.neon"));
    supportingFiles.add(new SupportingFile("phpcs.mustache", "", "phpcs.xml"));
    supportingFiles.add(new SupportingFile("composer.mustache", "", "composer.json"));
    supportingFiles.add(new SupportingFile("rector.mustache", "", "rector.php"));
    supportingFiles.add(new SupportingFile("phpmd.mustache", "", "phpmd.xml"));
    supportingFiles.add(new SupportingFile("grumphp.mustache", "", "grumphp.yml"));
    supportingFiles.add(new SupportingFile("gitignore.mustache", "", ".gitignore"));
    supportingFiles.add(new SupportingFile("mock.mustache", "", "Api".concat(clientClass.concat("Mock.php"))));
    supportingFiles.add(new SupportingFile("mockery.mustache", "", clientClass.concat("Mock.php")));

    if (additionalProperties.containsKey("factories")) {
        this.setSrcBasePath("Factory");
        modelTemplateFiles = new HashMap<>();
        modelTemplateFiles.put("factory.mustache", ".php");
        this.modelPackage = this.invokerPackage + "\\";
        this.modelNameSuffix = "Factory";
        this.fileSuffix = "Factory";
    }

    if (additionalProperties.containsKey("defaults")) {
        this.setSrcBasePath("Default");
        modelTemplateFiles = new HashMap<>();
        modelTemplateFiles.put("default.mustache", ".php");
        this.modelPackage = this.invokerPackage + "\\";
        this.modelNameSuffix = "Default";
        this.fileSuffix = "Default";
    }

    this.onlyJsonMediaTypes();
    this.onlyJsonComponents();
  }

  private void onlyJsonMediaTypes() {
    for (String pathName : openAPI.getPaths().keySet()) {
      PathItem pathItem = openAPI.getPaths().get(pathName);
      Map<HttpMethod, Operation> operations = pathItem.readOperationsMap();
      for (HttpMethod method : operations.keySet()) {
        Operation operation = operations.get(method);
        if (operation.getRequestBody() != null) {
          RequestBody requestBody = operation.getRequestBody();
          Content content = requestBody.getContent();
          if (content != null) {
            // remove all the media types except application/json
            MediaType mediaType = content.get("application/json");
              MediaType mediaTypePatch = content.get("application/merge-patch+json");
              if (mediaType != null || mediaTypePatch != null) {
                content.clear();
              }

                if (mediaType != null) {
                    content.addMediaType("application/json", mediaType);
                }

                if (mediaTypePatch != null) {
                    content.addMediaType("application/merge-patch+json", mediaTypePatch);
                }
          }
        }

        if (operation.getResponses() != null) {
          operation.getResponses().forEach((responseCode, response) -> {
            Content content = response.getContent();
            if (content != null) {
              // remove all the media types except application/json
              MediaType mediaType = content.get("application/json");
              MediaType mediaTypePatch = content.get("application/merge-patch+json");
              if (mediaType != null || mediaTypePatch != null) {
                content.clear();
              }

                if (mediaType != null) {
                    content.addMediaType("application/json", mediaType);
                }

                if (mediaTypePatch != null) {
                    content.addMediaType("application/merge-patch+json", mediaTypePatch);
                }
            }
          });
         }
      }
    }
  }

  private void onlyJsonComponents() {
    Components components = openAPI.getComponents();
    Map<String, Schema> schemas = components.getSchemas();
    schemas.keySet().removeIf(schemaName -> schemaName.contains(".jsonld") || schemaName.contains(".jsonhal"));
  }

  @Override
  public CodegenType getTag() {
    return CodegenType.CLIENT;
  }

  @Override
  public String getName() {
    return OpenApiGeneratorPhpClient.GENERATOR_NAME;
  }

  @Override
  public String toApiName(String name) {
    return org.openapitools.codegen.utils.StringUtils.camelize(name);
  }

  @Override
  public String toAnyOfName(List<String> names, Schema composedSchema) {
    return this.toComposedName(names, composedSchema);
  }

  @Override
  public String toOneOfName(List<String> names, Schema composedSchema) {
    return this.toComposedName(names, composedSchema);
  }

  @Override
  public String toAllOfName(List<String> names, Schema composedSchema) {
    return this.toComposedName(names, composedSchema);
  }

  private String toComposedName(List<String> names, Schema composedSchema) {
    if (ModelUtils.isStringSchema(composedSchema)) {
      return "string";
    }

    if (names.size() == 1) {
      String name = names.get(0);
      return this.toModelName(name);
    }

    return "mixed";
  }

  @Override
  @SuppressWarnings("static-method")
  public void addOperationToGroup(String tag, String resourcePath, Operation operation, CodegenOperation co, Map<String, List<CodegenOperation>> operations) {
      String uniqueName = co.operationId;
      co.operationIdLowerCase = uniqueName.toLowerCase(Locale.ROOT);
      co.operationIdCamelCase = org.openapitools.codegen.utils.StringUtils.camelize(uniqueName);
      co.operationIdSnakeCase = org.openapitools.codegen.utils.StringUtils.underscore(uniqueName);

      if (co.vendorExtensions == null) {
        co.vendorExtensions = new HashMap<>();
      }

      co.vendorExtensions.put("x-operation-scope", co.vendorExtensions.getOrDefault("x-operation-scope", "public"));
      co.vendorExtensions.put("x-add-to-documentation", co.vendorExtensions.getOrDefault("x-operation-scope", "public").equals("public"));

      operations.put(uniqueName, Arrays.asList(co));
  }

  @Override
  @SuppressWarnings("rawtypes")
  public String getTypeDeclaration(Schema p) {
    if (ModelUtils.isArraySchema(p)
        || ModelUtils.isMapSchema(p)
        || ModelUtils.isObjectSchema(p)
        || ModelUtils.isModel(p)
        || StringUtils.isNotBlank(p.get$ref())
    ) {
      return "array";
    }

    return super.getTypeDeclaration(p);
  }

  @Override
  public String getTypeDeclaration(String name) {
    if (!languageSpecificPrimitives.contains(name)) {
      return "array";
    }

    return super.getTypeDeclaration(name);
  }

  @Override
  public void preprocessOpenAPI(OpenAPI openAPI) {
    super.preprocessOpenAPI(openAPI);

    Paths paths = openAPI.getPaths();
    Components components = openAPI.getComponents();
    Map<String, Schema> schemas = components.getSchemas();

    for (String pathName : paths.keySet()) {
        PathItem pathItem = paths.get(pathName);
        Map<HttpMethod, Operation>  operations = pathItem.readOperationsMap();
        for(HttpMethod method: operations.keySet()) {
            Operation operation = operations.get(method);

            if (operation.getOperationId() == null) {
                operation.setOperationId(getOrGenerateOperationId(operation, pathName, method.toString().toLowerCase()));
            }

            if (operation.getParameters() != null) {
                this.createSchemaFromParameters(operation, components, "query");
            }

            if (operation.getRequestBody() != null) {
                this.createSchemaFromRequestBody(operation, components);
            }

            if (operation.getResponses() != null) {
                this.sortResponses(operation);
            }
        }
    }
  }

  @Override
  public List<CodegenParameter> fromRequestBodyToFormParameters(RequestBody body, Set<String> imports) {
        List<CodegenParameter> parameters = new ArrayList<CodegenParameter>();

        CodegenParameter parameter = CodegenModelFactory.newInstance(CodegenModelType.PARAMETER);
        parameter.baseType = body.getDescription();

        if (body.getRequired() != null) {
            parameter.required = body.getRequired();
        }

        parameters.add(parameter);

        return parameters;
  }

  @Override
  public String toApiImport(String name) {
      return apiPackage() + "\\" + name;
  }

  @Override
  public String toModelImport(String name) {
      if ("".equals(modelPackage())) {
          return name;
      } else {
          return modelPackage() + "\\" + name;
      }
  }

  @Override
  public String toModelName(final String name) {
      if (name.equals("mixed")) {
        return name;
      }

      if (name.equals("Object")) {
        return "\\stdClass";
      }

      return super.toModelName(name);
  }

  @Override
  public CodegenProperty fromProperty(String name, Schema p) {
      CodegenProperty property = super.fromProperty(name, p);

      if (property.openApiType.equals("mixed")) {
        property.complexType = "mixed";
      }

      return property;
  }

  @Override
  @SuppressWarnings("rawtypes")
  public CodegenParameter fromParameter(Parameter parameter, Set<String> imports) {
      CodegenParameter codegenParameter = super.fromParameter(parameter, imports);

      Schema schema = parameter.getSchema();
      if (schema != null && schema.getExtensions() != null) {
          Object voType = schema.getExtensions().get("x-vo-type");
          if (voType != null) {
              if (codegenParameter.vendorExtensions == null) {
                  codegenParameter.vendorExtensions = new HashMap<>();
              }
              codegenParameter.vendorExtensions.put("x-has-vo-type", true);
              codegenParameter.vendorExtensions.put("x-vo-type-fqn", "\\" + voType.toString());
          }
      }

      return codegenParameter;
  }

  private void createSchemaFromParameters(Operation operation, Components components, String inType) {
    List<Parameter> typeParameters = operation.getParameters()
        .stream()
        .filter(parameter -> (inType == "query" && parameter instanceof QueryParameter)
            || parameter.getIn() == inType
        )
        .collect(Collectors.toList());

        if (! typeParameters.isEmpty()) {
            String typeName = operation.getOperationId() + org.openapitools.codegen.utils.StringUtils.camelize(inType);

            Map<String, Schema> typeSchemas = typeParameters
                .stream()
                .collect(Collectors.toMap(Parameter::getName, Parameter::getSchema));

            List<String> requiredParameters = typeParameters
                .stream()
                .filter(parameter -> !(parameter.getRequired() == null || parameter.getRequired() == false))
                .map(parameter -> parameter.getName())
                .collect(Collectors.toList());

            ObjectSchema typeSchema = new ObjectSchema();
            typeSchema.setProperties(typeSchemas);
            typeSchema.setRequired(requiredParameters);

            components.addSchemas(typeName, typeSchema);
        }
  }

  private void createSchemaFromRequestBody(Operation operation, Components components) {
    if (operation.getRequestBody() == null) {
        return;
    }

    if (operation.getRequestBody().get$ref() != null) {
        this.createSchemaFromRefRequestBody(operation, components);

        return;
    }

    this.createSchemaFromFormData(operation, components);
  }

  private void createSchemaFromRefRequestBody(Operation operation, Components components) {
    Map<String, Schema> schemas = components.getSchemas();
    Map<String, RequestBody> requestBodies = components.getRequestBodies();
    RequestBody requestBody = operation.getRequestBody();

    String shortRef = ModelUtils.getSimpleRef(requestBody.get$ref());
    String newShortRef = operation.getOperationId() + "Body";

    // change the name in schemas
    Schema schema = schemas.get(shortRef);
    schemas.remove(shortRef);
    components.addSchemas(newShortRef, schema);

    // change the name in the requestBodies
    RequestBody componentRequestBody = requestBodies.get(shortRef);
    requestBodies.remove(shortRef);
    components.addRequestBodies(newShortRef, componentRequestBody);

    ModelUtils.getSchemaFromRequestBody(componentRequestBody).set$ref(newShortRef);
    requestBody.set$ref(newShortRef);
  }

  private void createSchemaFromFormData(Operation operation, Components components) {
    RequestBody requestBody = operation.getRequestBody();
    Schema schema = ModelUtils.getSchemaFromRequestBody(requestBody);
    String ref = operation.getOperationId() + "Body";

    // add new schema
    components.addSchemas(ref, schema);
    components.addRequestBodies(ref, requestBody);
    requestBody.set$ref(ref);
    requestBody.setDescription(org.openapitools.codegen.utils.StringUtils.camelize(ref));
  }

  private void sortResponses(Operation operation) {
    ApiResponses responses = operation.getResponses();
    Map<String, ApiResponse> sortedMap = new TreeMap<String, ApiResponse>(responses);

    responses.clear();
    for(String key : sortedMap.keySet()){
        responses.addApiResponse(key, sortedMap.get(key));
    }
  }

  @Override
  public void postProcessFile(File file, String fileType) {
      return;
  }

  @Override
  public ModelsMap postProcessModels(ModelsMap objs) {
        // Remove "double" vars that will lead to the same php property in a model
        // For example number and number[] will lead to the same property $number
        // So keep the number[] var and remove the number var
        objs = super.postProcessModels(objs);

        List<ModelMap> models = objs.getModels();
        ModelMap modelMap = models.get(0);
        CodegenModel model = modelMap.getModel();

        // Simplify model var type
        // define list of model vars to remove
        List<CodegenProperty> varsToRemove = new ArrayList<CodegenProperty>();
        for (Iterator<CodegenProperty> it = model.vars.iterator(); it.hasNext();) {
            CodegenProperty var = it.next();
            for (Iterator<CodegenProperty> it2 = model.vars.iterator(); it2.hasNext();) {
                CodegenProperty var2 = it2.next();
                if (var.baseName.equals(var2.baseName.concat("[]"))) {
                    // Replace the array notation without the array.
                    // The client adds the array twice
                    var.baseName = var2.baseName;
                    varsToRemove.add(var2);
                    break;
                }
            }
        }

        model.vars.removeAll(varsToRemove);
        model.allVars.removeAll(varsToRemove);
        model.requiredVars.removeAll(varsToRemove);
        model.optionalVars.removeAll(varsToRemove);
        model.readOnlyVars.removeAll(varsToRemove);
        model.readWriteVars.removeAll(varsToRemove);
        model.parentVars.removeAll(varsToRemove);

        return objs;
  }

  private void resetTemplateFiles() {
    this.supportingFiles.clear();
    this.apiTemplateFiles.clear();
    this.apiTestTemplateFiles.clear();
    this.apiDocTemplateFiles.clear();
    this.modelTestTemplateFiles.clear();
    this.modelDocTemplateFiles.clear();

    apiTemplateFiles.put("api.mustache", ".php");
  }

  @Override
  public String toEnumDefaultValue(String value, String datatype) {
    return value;
  }

  @Override
  public String toEnumVarName(String value, String datatype) { return value; }

  @Override
  public boolean isDataTypeString(String dataType) {
    return "String".equalsIgnoreCase(dataType);
  }

  @Override
  public String toVarName(String name) {
    if (name.matches("^\\d.*")) {
      return "prefixNumber" + name;
    }

    if (name.matches("^@.*")) {
      name = "_at" + name;
    }

    return super.toVarName(name);
  }

  @Override
  public String toGetter(String name) {
    return getterAndSetterCapitalize(name);
  }

  @Override
  public String toSetter(String name) {
    return getterAndSetterCapitalize(name);
  }

  @Override
  public String toBooleanGetter(String name) {
    return getterAndSetterCapitalize(name);
  }

  @Override
  public String getterAndSetterCapitalize(String name) {
    if (name == null || name.length() == 0) {
        return name;
    }
    return toVarName(name);
  }

  @Override
  public String toDefaultValue(Schema schema) {
    if (schema.getDefault() == null) {
      return super.toDefaultValue(schema);
    }

    if (ModelUtils.isStringSchema(schema)) {
        // escape single quotes in schema.getDefault().toString()
        return schema.getDefault().toString().replace("'", "\\'");
    }

    if (! ModelUtils.isArraySchema(schema)) {
      return super.toDefaultValue(schema);
    }

    ArraySchema arraySchema = (ArraySchema) schema;
    ArrayNode arrayNode = (ArrayNode) arraySchema.getDefault();
    if (arraySchema.getItems() != null
         && arrayNode.isEmpty() == false
         && ModelUtils.isStringSchema(arraySchema.getItems())
    ) {
        return arraySchema.getDefault().toString().replace("[", "['").replace("]", "']").replace(", ", "', '");
    }

    return arraySchema.getDefault().toString();
  }

  // added because uploading file parameters give problems
  @Override
  public boolean isParameterNameUnique(CodegenParameter p, List<CodegenParameter> parameters) {
      if (p.paramName == null) {
          return true;
      }

      return super.isParameterNameUnique(p, parameters);
  }
}
